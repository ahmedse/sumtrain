@mod @mod_sumtrain
Feature: Test the display of the sumtrain module on my home
  In order to know my status in a sumtrain activity
  As a user
  I need to see it in My dashboard.

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
    And the following "activities" exist:
      | activity | name             | intro                   | course | idnumber | option             | section |
      | sumtrain   | Test sumtrain name | Test sumtrain description | C1     | sumtrain1  | Option 1, Option 2 | 1       |
    And I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I follow "Test sumtrain name"
    And I navigate to "Settings" in current page administration
    And I set the following fields to these values:
      | timeopen[enabled] | 1 |
      | timeclose[enabled] | 1 |
      | timeclose[day] | 1 |
      | timeclose[month] | January |
      | timeclose[year] | 2030 |
      | timeclose[hour] | 08 |
      | timeclose[minute] | 00 |
      | Allow sumtrain to be updated | No |
    And I press "Save and return to course"
    And I log out

  Scenario: View my home as a student after answering the sumtrain
    Given I log in as "student1"
    And I am on "Course 1" course homepage
    And I choose "Option 1" from "Test sumtrain name" sumtrain activity
    And I should see "Your selection: Option 1"
    And I should see "Your sumtrain has been saved"
    And "Save my sumtrain" "button" should not exist
    And I log out

  Scenario: View my home as a teacher
    Given I log in as "student1"
    And I am on "Course 1" course homepage
    And I choose "Option 1" from "Test sumtrain name" sumtrain activity
    And I should see "Your selection: Option 1"
    And I should see "Your sumtrain has been saved"
    And "Save my sumtrain" "button" should not exist
    And I log out
