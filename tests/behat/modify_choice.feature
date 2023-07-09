@mod @mod_sumtrain
Feature: Teacher can modify sumtrains of the students
  In order to have all students sumtrains
  As a teacher
  I need to be able to make sumtrain for studnets

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email |
      | teacher1 | Teacher | 1 | teacher1@example.com |
      | student1 | Student | 1 | student1@example.com |
      | student2 | Student | 2 | student2@example.com |
      | student3 | Student | 3 | student3@example.com |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1 | 0 |
    And the following "course enrolments" exist:
      | user | course | role |
      | teacher1 | C1 | editingteacher |
      | student1 | C1 | student |
      | student2 | C1 | student |
      | student3 | C1 | student |
    And the following "activities" exist:
      | activity | name        | intro                   | course | idnumber | option |
      | sumtrain   | sumtrain name | Test sumtrain description | C1     | sumtrain1  | Option 1, Option 2, Option 3 |

  @javascript
  Scenario: Delete students sumtrain response as a teacher
    Given I am on the "sumtrain name" "sumtrain activity editing" page logged in as teacher1
    And I expand all fieldsets
    And I set the field "Show column for unanswered" to "Yes"
    And I press "Save and return to course"
    And I log out
    And I log in as "student1"
    And I am on "Course 1" course homepage
    And I choose "Option 1" from "sumtrain name" sumtrain activity
    Then I should see "Your selection: Option 1"
    And I should see "Your sumtrain has been saved"
    And I log out
    And I log in as "teacher1"
    And I change window size to "large"
    And I am on the "sumtrain name" "sumtrain activity" page
    And I navigate to "Responses" in current page administration
    And I click on "Student 1 Option 1" "checkbox"
    And I select "Delete" from the "With selected" singleselect
    And "Student 1 Option 1" "checkbox" should not exist
    And "Student 1 Not answered yet" "checkbox" should exist

  @javascript
  Scenario: Teacher set answers of students who did not respond or change existing answers
    Given I am on the "sumtrain name" "sumtrain activity editing" page logged in as teacher1
    And I expand all fieldsets
    And I set the field "Show column for unanswered" to "Yes"
    And I press "Save and return to course"
    And I log out
    And I am on the "Course 1" course page logged in as student1
    And I choose "Option 1" from "sumtrain name" sumtrain activity
    Then I should see "Your selection: Option 1"
    And I should see "Your sumtrain has been saved"
    And I log out
    And I change window size to "large"
    And I am on the "sumtrain name" "sumtrain activity" page logged in as teacher1
    And I navigate to "Responses" in current page administration
    And I click on "Student 1 Option 1" "checkbox"
    And I click on "Student 2 Not answered yet" "checkbox"
    And I click on "Student 3 Not answered yet" "checkbox"
    And I select "Choose: Option 2" from the "With selected" singleselect
    And "Student 1 Option 1" "checkbox" should not exist
    And "Student 2 Not answered yet" "checkbox" should not exist
    And "Student 3 Not answered yet" "checkbox" should not exist
    And "Student 1 Option 2" "checkbox" should exist
    And "Student 2 Option 2" "checkbox" should exist
    And "Student 3 Option 2" "checkbox" should exist

  @javascript
  Scenario: Teacher can delete answers in the multiple answer sumtrain
    Given I am on the "sumtrain name" "sumtrain activity editing" page logged in as teacher1
    And I set the field "Allow more than one sumtrain to be selected" to "Yes"
    And I press "Save and return to course"
    And I log out
    And I am on the "Course 1" course page logged in as student1
    And I choose options "Option 1","Option 2" from "sumtrain name" sumtrain activity
    And I should see "Your selection: Option 1; Option 2"
    And I should see "Your sumtrain has been saved"
    And I log out
    And I change window size to "large"
    And I am on the "sumtrain name" "sumtrain activity" page logged in as teacher1
    And I navigate to "Responses" in current page administration
    And I click on "Student 1 Option 2" "checkbox"
    And I select "Delete" from the "With selected" singleselect
    And I click on "Student 1 Option 1" "checkbox"
    And I select "Choose: Option 3" from the "With selected" singleselect
    And I log out
    And I am on the "sumtrain name" "sumtrain activity" page logged in as student1
    And I should see "Your selection: Option 1; Option 3"

  @javascript
  Scenario: Teacher can manage answers on view page if the names are displayed
    Given I am on the "Course 1" course page logged in as student1
    And I choose "Option 1" from "sumtrain name" sumtrain activity
    Then I should see "Your selection: Option 1"
    And I should see "Your sumtrain has been saved"
    And I log out
    And I change window size to "large"
    And I am on the "sumtrain name" "sumtrain activity editing" page logged in as teacher1
    And I set the following fields to these values:
      | Publish results | Always show results to students |
      | Privacy of results | Publish full results, showing names and their sumtrains |
      | Show column for unanswered | Yes |
    And I press "Save and display"
    And I click on "Student 1 Option 1" "checkbox"
    And I click on "Student 2 Not answered yet" "checkbox"
    And I select "Choose: Option 3" from the "With selected" singleselect
    And "Student 1 Option 1" "checkbox" should not exist
    And "Student 1 Option 3" "checkbox" should exist
    And "Student 2 Not answered yet" "checkbox" should not exist
    And "Student 2 Option 3" "checkbox" should exist
    And I click on "Student 1 Option 3" "checkbox"
    And I select "Delete" from the "With selected" singleselect
    And "Student 1 Option 3" "checkbox" should not exist
    And "Student 1 Not answered yet" "checkbox" should exist
