@mod @mod_sumtrain
Feature: Editing sumtrain block
  In order to customise sumtrain page
  As a teacher or admin
  I need to add remove block from the sumtrain page

  # This tests that the hacky block editing is not borked by legacy forms in sumtrain activity.
  Scenario: Add a sumtrain activity as admin and check blog menu block should contain link.
    Given the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1 | 0 |
    And the following "activity" exists:
      | activity | sumtrain               |
      | course   | C1                   |
      | idnumber | sumtrain1              |
      | name     | sumtrain name 1        |
      | intro    | sumtrain Description 1 |
      | section  | 1                    |
      | option   | Option 1, Option 2   |
    And I log in as "admin"
    And I am on "Course 1" course homepage with editing mode on
    And I follow "sumtrain name 1"
    And I add the "Blog menu" block
    And I should see "View all entries about this sumtrain"
    When I configure the "Blog menu" block
    And I press "Save changes"
    Then I should see "View all entries about this sumtrain"
    And I open the "Blog menu" blocks action menu
    And I click on "Delete" "link" in the "Blog menu" "block"
    And I press "Yes"
    And I should not see "View all entries about this sumtrain"
    And I should see "sumtrain Description 1"

  Scenario: Add a sumtrain activity as teacher and check blog menu block contain sumtrain link.
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
    And the following "activity" exists:
      | activity | sumtrain               |
      | course   | C1                   |
      | idnumber | sumtrain1              |
      | name     | sumtrain name 1        |
      | intro    | sumtrain Description 1 |
      | section  | 1                    |
      | option   | Option 1, Option 2   |
    And I log in as "teacher1"
    And I am on "Course 1" course homepage with editing mode on
    And I follow "sumtrain name 1"
    And I add the "Blog menu" block
    And I should see "View all entries about this sumtrain"
    When I configure the "Blog menu" block
    And I press "Save changes"
    Then I should see "View all entries about this sumtrain"
    And I open the "Blog menu" blocks action menu
    And I click on "Delete" "link" in the "Blog menu" "block"
    And I press "Yes"
    And I should not see "View all entries about this sumtrain"
    And I should see "sumtrain Description 1"

  Scenario: Add a sumtrain activity as teacher (with dual role) and check blog menu block contain sumtrain link.
    Given the following "users" exist:
      | username | firstname | lastname | email |
      | teacher1 | Teacher | 1 | teacher1@example.com |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1 | 0 |
    And the following "course enrolments" exist:
      | user | course | role |
      | teacher1 | C1 | editingteacher |
      | teacher1 | C1 | student |
    And the following "activity" exists:
      | activity | sumtrain               |
      | course   | C1                   |
      | idnumber | sumtrain1              |
      | name     | sumtrain name 1        |
      | intro    | sumtrain Description 1 |
      | section  | 1                    |
      | option   | Option 1, Option 2   |
    And I log in as "teacher1"
    And I am on "Course 1" course homepage with editing mode on
    And I follow "sumtrain name 1"
    And I add the "Blog menu" block
    And I should see "View all entries about this sumtrain"
    When I configure the "Blog menu" block
    And I press "Save changes"
    Then I should see "View all entries about this sumtrain"
    And I open the "Blog menu" blocks action menu
    And I click on "Delete" "link" in the "Blog menu" "block"
    And I press "Yes"
    And I should not see "View all entries about this sumtrain"
    And I should see "sumtrain Description 1"
