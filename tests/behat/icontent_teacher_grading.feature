@mod @mod_icontent
Feature: iContent teacher manual review dashboard
  In order to grade iContent manual-review items
  As a teacher
  I need to access and understand the manual review dashboard

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | One      | teacher1@example.com |
      | student1 | Student   | One      | student1@example.com |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |
    And the following "activities" exist:
      | activity | name               | intro         | course | idnumber         |
      | icontent | iContent With Page | Content intro | C1     | icontent-withone |
    And the icontent "iContent With Page" has a page titled "Welcome page" with content "Welcome to iContent learning"

  Scenario: Teacher can access iContent manual review dashboard
    Given I log in as "teacher1"
    When I am on the "iContent With Page" icontent manual review page
    Then I should see "Students with questions that need to be evaluated manually"
    And I should see "To evaluate"
    And I should see "Action"

  Scenario: Teacher sees no-records and evaluated-info messages when nothing is pending
    Given I log in as "teacher1"
    When I am on the "iContent With Page" icontent manual review page
    Then I should see "No records found."
    And I should see "The answers that have been evaluated do not appear in this list."
