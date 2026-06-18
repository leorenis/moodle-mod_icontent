@mod @mod_icontent
Feature: iContent student flow
  In order to learn using iContent
  As a student
  I need to access authored content and be restricted from grading tools

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
      | icontent | iContent Empty     | Empty intro   | C1     | icontent-empty   |
      | icontent | iContent With Page | Content intro | C1     | icontent-withone |

  Scenario: Student sees informative empty-state in activity without pages
    When I am on the "iContent Empty" "mod_icontent > View" page logged in as "student1"
    Then I should see "No page found for this instance of iContent."

  Scenario: Student sees authored iContent page content
    Given the icontent "iContent With Page" has a page titled "Welcome page" with content "Welcome to iContent learning"
    When I am on the "iContent With Page" "mod_icontent > View" page logged in as "student1"
    Then I should see "Welcome page"
    And I should see "Welcome to iContent learning"
    And I should not see "No page found for this instance of iContent."

  Scenario: Student cannot access iContent manual review page
    Given I log in as "student1"
    And I am on the "iContent With Page" icontent manual review page
    Then I should see "Sorry, but you do not currently have permissions to do that"
