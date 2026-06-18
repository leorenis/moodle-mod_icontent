@mod @mod_icontent
Feature: iContent question management on page view
  In order to maintain page questions safely
  As an editing teacher
  I need remove controls in edit mode and a working remove action

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
      | questioncategory | qtype       | name             | template    |
      | Test questions   | multichoice | Remove test Q1   | one_of_four |
      | Test questions   | multichoice | Remove blocked Q1| one_of_four |
      | Test questions   | multichoice | Toolbar delete Q1| one_of_four |
      | Test questions   | multichoice | TOC delete Q1    | one_of_four |
    And the following "activities" exist:
      | activity | name                     | intro         | course | idnumber              |
      | icontent | iContent Question Manage | Content intro | C1     | icontent-qm           |
    And the icontent "iContent Question Manage" has a page titled "Question page" with content "Question content"
    And the icontent "iContent Question Manage" page "Question page" links question "Remove test Q1"

  Scenario: Editing teacher sees remove icon and can remove linked question
    When I am on the "iContent Question Manage" "mod_icontent > View" page logged in as "teacher1"
    And I turn editing mode on
    Then ".icon-removequestion" "css_element" should exist
    When I remove question "Remove test Q1" from page "Question page" in icontent "iContent Question Manage"
    Then I should see "Successfully deleted records!"
    And the icontent "iContent Question Manage" should not include page "Question page" with question "Remove test Q1"

  Scenario: Editing teacher does not see remove icon when attempts already exist
    Given the icontent "iContent Question Manage" page "Question page" links question "Remove blocked Q1" and has a graded attempt for "student1" with answer "Choice A"
    When I am on the "iContent Question Manage" "mod_icontent > View" page logged in as "teacher1"
    And I turn editing mode on
    Then ".icon-removequestion" "css_element" should not exist

  Scenario: Editing teacher can delete a page from the toolbar and related data is removed
    Given the icontent "iContent Question Manage" has a page titled "Toolbar delete page" with content "Toolbar delete content"
    And the icontent "iContent Question Manage" page "Toolbar delete page" links question "Toolbar delete Q1" and has a graded attempt for "student1" with answer "Choice A"
    And the icontent "iContent Question Manage" page "Toolbar delete page" has a note "Toolbar note" by "student1" liked by "teacher1"
    When I am on the "iContent Question Manage" "mod_icontent > View" page logged in as "teacher1"
    And I turn editing mode on
    And I delete page "Toolbar delete page" from the toolbar in icontent "iContent Question Manage"
    Then the icontent "iContent Question Manage" page "Toolbar delete page" should be fully deleted

  Scenario: Editing teacher can delete a page from the TOC and related data is removed
    Given the icontent "iContent Question Manage" has a page titled "TOC delete page" with content "TOC delete content"
    And the icontent "iContent Question Manage" page "TOC delete page" links question "TOC delete Q1" and has a graded attempt for "student1" with answer "Choice A"
    And the icontent "iContent Question Manage" page "TOC delete page" has a note "TOC note" by "student1" liked by "teacher1"
    When I am on the "iContent Question Manage" "mod_icontent > View" page logged in as "teacher1"
    And I turn editing mode on
    And I delete page "TOC delete page" from the TOC in icontent "iContent Question Manage"
    Then the icontent "iContent Question Manage" page "TOC delete page" should be fully deleted
