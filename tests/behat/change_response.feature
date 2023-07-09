@mod @mod_sumtrain
Feature: Teacher can choose whether to allow students to change their sumtrain response
  In order to allow students to change their sumtrain
  As a teacher
  I need to enable the option to change the sumtrain

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email |
      | teacher1 | Teacher | 1 | teacher1@example.com |
      | student1 | Student | 1 | student1@example.com |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1 | 0 |
    And the following "course enrolments" exist:
      | user | course | role |
      | teacher1 | C1 | editingteacher |
      | student1 | C1 | student |

  Scenario: Change a sumtrain response as a student
    Given the following "activity" exists:
      | activity    | sumtrain             |
      | course      | C1                 |
      | idnumber    | sumtrain name        |
      | name        | sumtrain name        |
      | intro       | sumtrain Description |
      | section     | 1                  |
      | option      | Option 1, Option 2 |
      | allowupdate | 0                  |
    When I am on the "Course 1" course page logged in as student1
    And I choose "Option 1" from "sumtrain name" sumtrain activity
    And I should see "Your selection: Option 1"
    And I should see "Your sumtrain has been saved"
    Then "Save my sumtrain" "button" should not exist

  Scenario: Change a sumtrain response as a student
    Given the following "activity" exists:
      | activity    | sumtrain             |
      | course      | C1                 |
      | idnumber    | sumtrain name        |
      | name        | sumtrain name        |
      | intro       | sumtrain Description |
      | section     | 1                  |
      | option      | Option 1, Option 2 |
      | allowupdate | 1                  |
    When I am on the "Course 1" course page logged in as student1
    And I choose "Option 1" from "sumtrain name" sumtrain activity
    And I should see "Your selection: Option 1"
    And I should see "Your sumtrain has been saved"
    Then I should see "Your selection: Option 1"
    And "Save my sumtrain" "button" should exist
    And "Remove my sumtrain" "link" should exist
    And I set the field "Option 2" to "1"
    And I press "Save my sumtrain"
    And I should see "Your sumtrain has been saved"
    And I should see "Your selection: Option 2"
