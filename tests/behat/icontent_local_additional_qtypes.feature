@mod @mod_icontent @local
Feature: Local iContent additional question type inventory
  In order to validate this installation's non-core iContent question support
  As a maintainer
  I need to verify additional qtypes exist in the Questions Testing activity

  Scenario: Questions Testing includes all local additional qtypes
    Then the icontent "Questions Testing" should include question type "ddwtos"
    And the icontent "Questions Testing" should include question type "formulas"
    And the icontent "Questions Testing" should include question type "gapfill"
    And the icontent "Questions Testing" should include question type "poodllrecording"
    And the icontent "Questions Testing" should include question type "stack"
    And the icontent "Questions Testing" should include question type "varnumeric"
