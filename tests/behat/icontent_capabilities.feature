@mod @mod_icontent
Feature: iContent core workflows and permissions
  In order to safely evolve iContent
  As a plugin maintainer
  I need Behat coverage for iContent-specific flows and capabilities

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

  Scenario: Editing teacher is prompted to create the first page in an empty iContent
    When I am on the "iContent Empty" "mod_icontent > View" page logged in as "teacher1"
    Then I should see "Add new page"
    And I should see "Page title"

  Scenario: Student sees informative empty-state message
    When I am on the "iContent Empty" "mod_icontent > View" page logged in as "student1"
    Then I should see "No page found for this instance of iContent."

  Scenario: Student can view iContent page content created for the activity
    Given the icontent "iContent With Page" has a page titled "Welcome page" with content "Welcome to iContent learning"
    When I am on the "iContent With Page" "mod_icontent > View" page logged in as "student1"
    Then I should see "Welcome page"
    And I should see "Welcome to iContent learning"

  Scenario: Manual review page is restricted to users with grading capability
    Given I log in as "teacher1"
    And I am on the "iContent With Page" icontent manual review page
    Then I should see "Students with questions that need to be evaluated manually"
    And I log out
    Given I log in as "student1"
    And I am on the "iContent With Page" icontent manual review page
    Then I should see "Sorry, but you do not currently have permissions to do that"
