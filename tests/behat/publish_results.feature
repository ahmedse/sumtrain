@mod @mod_sumtrain
Feature: A teacher can choose one of 4 options for publishing sumtrain results
  In order to display sumtrain activities outcomes
  As a teacher
  I need to publish the sumtrain activity results in different ways

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
    And I log in as "teacher1"
    And I am on "Course 1" course homepage with editing mode on

  Scenario: Do not publish results to students
    Given the following "activity" exists:
      | activity | sumtrain               |
      | course   | C1                   |
      | idnumber | sumtrain1              |
      | name     | sumtrain 1             |
      | intro    | sumtrain Description   |
      | section  | 1                    |
      | option   | Option 1, Option 2   |
    And I am on "Course 1" course homepage
    And I follow "sumtrain 1"
    And I navigate to "Settings" in current page administration
    And I set the following fields to these values:
      | Publish results | Do not publish results to students |
    And I press "Save and return to course"
    And I log out
    And I log in as "student1"
    And I am on "Course 1" course homepage
    When I choose "Option 1" from "sumtrain 1" sumtrain activity
    Then I should see "Your selection: Option 1"
    And I should not see "Responses"
    And I should not see "Graph display"

  Scenario: Show results to students after they answer
    Given the following "activity" exists:
      | activity | sumtrain               |
      | course   | C1                   |
      | idnumber | sumtrain1              |
      | name     | sumtrain 1             |
      | intro    | sumtrain Description   |
      | section  | 1                    |
      | option   | Option 1, Option 2   |
    And I am on "Course 1" course homepage
    And I follow "sumtrain 1"
    And I navigate to "Settings" in current page administration
    And I set the following fields to these values:
      | Publish results | Show results to students after they answer |
    And I press "Save and return to course"
    And I log out
    And I log in as "student1"
    And I am on "Course 1" course homepage
    When I follow "sumtrain 1"
    Then I should not see "Responses"
    And I am on "Course 1" course homepage
    And I choose "Option 1" from "sumtrain 1" sumtrain activity
    And I should see "Your selection: Option 1"
    And I should see "Responses"

  Scenario: Show results to students only after the sumtrain is closed
    Given the following "activity" exists:
      | activity | sumtrain               |
      | course   | C1                   |
      | idnumber | sumtrain1              |
      | name     | sumtrain 1             |
      | intro    | sumtrain Description   |
      | section  | 1                    |
      | option   | Option 1, Option 2   |
    And I am on "Course 1" course homepage
    And I follow "sumtrain 1"
    And I navigate to "Settings" in current page administration
    And I set the following fields to these values:
      | Publish results | Show results to students only after the sumtrain is closed |
    And I press "Save and return to course"
    And I log out
    And I log in as "student1"
    And I am on "Course 1" course homepage
    When I follow "sumtrain 1"
    Then I should not see "Responses"
    And I choose "Option 1" from "sumtrain 1" sumtrain activity
    And I log out
    And I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I follow "sumtrain 1"
    And I follow "Settings"
    And I expand all fieldsets
    And I set the following fields to these values:
      | timeopen[enabled] | 1 |
      | timeopen[day] | 1 |
      | timeopen[month] | January |
      | timeopen[year] | 2010 |
      | timeclose[enabled] | 1 |
      | timeclose[day] | 2 |
      | timeclose[month] | January |
      | timeclose[year] | 2010 |
    And I press "Save and return to course"
    And I log out
    And I log in as "student1"
    And I am on "Course 1" course homepage
    And I follow "sumtrain 1"
    And I should see "Responses"

  Scenario: Always show results to students
    Given the following "activity" exists:
      | activity | sumtrain               |
      | course   | C1                   |
      | idnumber | sumtrain1              |
      | name     | sumtrain 1             |
      | intro    | sumtrain Description   |
      | section  | 1                    |
      | option   | Option 1, Option 2   |
    And I am on "Course 1" course homepage
    And I follow "sumtrain 1"
    And I navigate to "Settings" in current page administration
    And I set the following fields to these values:
      | Publish results | Always show results to students |
    And I press "Save and return to course"
    And I log out
    And I log in as "student1"
    And I am on "Course 1" course homepage
    When I follow "sumtrain 1"
    And I should see "Responses"
