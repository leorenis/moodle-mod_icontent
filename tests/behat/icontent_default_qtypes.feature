@mod @mod_icontent
Feature: iContent default question type essay pending review
  In order to validate iContent support for a default Moodle question type
  As a maintainer
  I need pending essay attempts to render correctly in student results

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
    And the following "question categories" exist:
      | contextlevel | reference | name           |
      | Course       | C1        | Test questions |
    And the following "questions" exist:
      | questioncategory | qtype | name     | questiontext            |
      | Test questions   | essay | Essay Q1 | Explain your reasoning. |
    And the following "activities" exist:
      | activity | name                   | intro         | course | idnumber                |
      | icontent | iContent Essay Pending | Content intro | C1     | icontent-essay-pending  |
    And the icontent "iContent Essay Pending" has a page titled "Essay page" with content "Please answer the essay question"

  Scenario: Student sees pending state and submitted essay answer in result summary
    Given the icontent "iContent Essay Pending" page "Essay page" links question "Essay Q1" and has a pending attempt for "student1" with answer "My pending essay answer"
    When I am on the "iContent Essay Pending" "mod_icontent > View" page logged in as "student1"
    Then I should see "Result of the last attempt"
    And I should see "Pending"
    And I should see "Essay Q1"
    And I should see "My pending essay answer"
    And I should see "response awaiting evaluation"
