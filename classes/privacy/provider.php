<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Privacy Subsystem implementation for mod_sumtrain.
 *
 * @package    mod_sumtrain
 * @category   privacy
 * @copyright  2018 Jun Pataleta
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_sumtrain\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\deletion_criteria;
use core_privacy\local\request\helper;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

defined('MOODLE_INTERNAL') || die();

/**
 * Implementation of the privacy subsystem plugin provider for the sumtrain activity module.
 *
 * @copyright  2018 Jun Pataleta
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
        // This plugin stores personal data.
        \core_privacy\local\metadata\provider,

        // This plugin is a core_user_data_provider.
        \core_privacy\local\request\plugin\provider,

        // This plugin is capable of determining which users have data within it.
        \core_privacy\local\request\core_userlist_provider {
    /**
     * Return the fields which contain personal data.
     *
     * @param collection $items a reference to the collection to use to store the metadata.
     * @return collection the updated collection of metadata items.
     */
    public static function get_metadata(collection $items) : collection {
        $items->add_database_table(
            'sumtrain_answers',
            [
                'sumtrainid' => 'privacy:metadata:sumtrain_answers:sumtrainid',
                'optionid' => 'privacy:metadata:sumtrain_answers:optionid',
                'userid' => 'privacy:metadata:sumtrain_answers:userid',
                'timemodified' => 'privacy:metadata:sumtrain_answers:timemodified',
            ],
            'privacy:metadata:sumtrain_answers'
        );

        return $items;
    }

    /**
     * Get the list of contexts that contain user information for the specified user.
     *
     * @param int $userid the userid.
     * @return contextlist the list of contexts containing user info for the user.
     */
    public static function get_contexts_for_userid(int $userid) : contextlist {
        // Fetch all sumtrain answers.
        $sql = "SELECT c.id
                  FROM {context} c
            INNER JOIN {course_modules} cm ON cm.id = c.instanceid AND c.contextlevel = :contextlevel
            INNER JOIN {modules} m ON m.id = cm.module AND m.name = :modname
            INNER JOIN {sumtrain} ch ON ch.id = cm.instance
            INNER JOIN {sumtrain_options} co ON co.sumtrainid = ch.id
            INNER JOIN {sumtrain_answers} ca ON ca.optionid = co.id AND ca.sumtrainid = ch.id
                 WHERE ca.userid = :userid";

        $params = [
            'modname'       => 'sumtrain',
            'contextlevel'  => CONTEXT_MODULE,
            'userid'        => $userid,
        ];
        $contextlist = new contextlist();
        $contextlist->add_from_sql($sql, $params);

        return $contextlist;
    }

    /**
     * Get the list of users who have data within a context.
     *
     * @param   userlist    $userlist   The userlist containing the list of users who have data in this context/plugin combination.
     */
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();

        if (!$context instanceof \context_module) {
            return;
        }

        // Fetch all sumtrain answers.
        $sql = "SELECT ca.userid
                  FROM {course_modules} cm
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                  JOIN {sumtrain} ch ON ch.id = cm.instance
                  JOIN {sumtrain_options} co ON co.sumtrainid = ch.id
                  JOIN {sumtrain_answers} ca ON ca.optionid = co.id AND ca.sumtrainid = ch.id
                 WHERE cm.id = :cmid";

        $params = [
            'cmid'      => $context->instanceid,
            'modname'   => 'sumtrain',
        ];

        $userlist->add_from_sql('userid', $sql, $params);
    }

    /**
     * Export personal data for the given approved_contextlist. User and context information is contained within the contextlist.
     *
     * @param approved_contextlist $contextlist a list of contexts approved for export.
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;

        if (empty($contextlist->count())) {
            return;
        }

        $user = $contextlist->get_user();

        list($contextsql, $contextparams) = $DB->get_in_or_equal($contextlist->get_contextids(), SQL_PARAMS_NAMED);

        $sql = "SELECT cm.id AS cmid,
                       co.text as answer,
                       ca.timemodified
                  FROM {context} c
            INNER JOIN {course_modules} cm ON cm.id = c.instanceid AND c.contextlevel = :contextlevel
            INNER JOIN {modules} m ON m.id = cm.module AND m.name = :modname
            INNER JOIN {sumtrain} ch ON ch.id = cm.instance
            INNER JOIN {sumtrain_options} co ON co.sumtrainid = ch.id
            INNER JOIN {sumtrain_answers} ca ON ca.optionid = co.id AND ca.sumtrainid = ch.id
                 WHERE c.id {$contextsql}
                       AND ca.userid = :userid
              ORDER BY cm.id";

        $params = ['modname' => 'sumtrain', 'contextlevel' => CONTEXT_MODULE, 'userid' => $user->id] + $contextparams;

        // Reference to the sumtrain activity seen in the last iteration of the loop. By comparing this with the current record, and
        // because we know the results are ordered, we know when we've moved to the answers for a new sumtrain activity and therefore
        // when we can export the complete data for the last activity.
        $lastcmid = null;

        $sumtrainanswers = $DB->get_recordset_sql($sql, $params);
        foreach ($sumtrainanswers as $sumtrainanswer) {
            // If we've moved to a new sumtrain, then write the last sumtrain data and reinit the sumtrain data array.
            if ($lastcmid != $sumtrainanswer->cmid) {
                if (!empty($sumtraindata)) {
                    $context = \context_module::instance($lastcmid);
                    self::export_sumtrain_data_for_user($sumtraindata, $context, $user);
                }
                $sumtraindata = [
                    'answer' => [],
                    'timemodified' => \core_privacy\local\request\transform::datetime($sumtrainanswer->timemodified),
                ];
            }
            $sumtraindata['answer'][] = $sumtrainanswer->answer;
            $lastcmid = $sumtrainanswer->cmid;
        }
        $sumtrainanswers->close();

        // The data for the last activity won't have been written yet, so make sure to write it now!
        if (!empty($sumtraindata)) {
            $context = \context_module::instance($lastcmid);
            self::export_sumtrain_data_for_user($sumtraindata, $context, $user);
        }
    }

    /**
     * Export the supplied personal data for a single sumtrain activity, along with any generic data or area files.
     *
     * @param array $sumtraindata the personal data to export for the sumtrain.
     * @param \context_module $context the context of the sumtrain.
     * @param \stdClass $user the user record
     */
    protected static function export_sumtrain_data_for_user(array $sumtraindata, \context_module $context, \stdClass $user) {
        // Fetch the generic module data for the sumtrain.
        $contextdata = helper::get_context_data($context, $user);

        // Merge with sumtrain data and write it.
        $contextdata = (object)array_merge((array)$contextdata, $sumtraindata);
        writer::with_context($context)->export_data([], $contextdata);

        // Write generic module intro files.
        helper::export_context_files($context, $user);
    }

    /**
     * Delete all data for all users in the specified context.
     *
     * @param \context $context the context to delete in.
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;

        if (!$context instanceof \context_module) {
            return;
        }

        if ($cm = get_coursemodule_from_id('sumtrain', $context->instanceid)) {
            $DB->delete_records('sumtrain_answers', ['sumtrainid' => $cm->instance]);
        }
    }

    /**
     * Delete all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist a list of contexts approved for deletion.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;

        if (empty($contextlist->count())) {
            return;
        }

        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {

            if (!$context instanceof \context_module) {
                continue;
            }
            $instanceid = $DB->get_field('course_modules', 'instance', ['id' => $context->instanceid]);
            if (!$instanceid) {
                continue;
            }
            $DB->delete_records('sumtrain_answers', ['sumtrainid' => $instanceid, 'userid' => $userid]);
        }
    }

    /**
     * Delete multiple users within a single context.
     *
     * @param   approved_userlist       $userlist The approved context and user information to delete information for.
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        global $DB;

        $context = $userlist->get_context();

        if (!$context instanceof \context_module) {
            return;
        }

        $cm = get_coursemodule_from_id('sumtrain', $context->instanceid);

        if (!$cm) {
            // Only sumtrain module will be handled.
            return;
        }

        $userids = $userlist->get_userids();
        list($usersql, $userparams) = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);

        $select = "sumtrainid = :sumtrainid AND userid $usersql";
        $params = ['sumtrainid' => $cm->instance] + $userparams;
        $DB->delete_records_select('sumtrain_answers', $select, $params);
    }
}
