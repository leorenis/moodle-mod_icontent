@mod @mod_icontent @local
Feature: Local Questions Testing page-question inventory
  In order to preserve this installation's Questions Testing setup
  As a maintainer
  I need to verify each configured page/question mapping still exists

  Scenario: Questions Testing contains expected page-question mappings
    Then the icontent "Questions Testing" should include page "Content Pages" with question "test" of type "gapfill"
    And the icontent "Questions Testing" should include page "MC Multi-Choice" with question "MC Helicopter" of type "multichoice"
    And the icontent "Questions Testing" should include page "TF True/False" with question "T/F New question for testing" of type "truefalse"
    And the icontent "Questions Testing" should include page "MA Matching" with question "MA Helicopter" of type "match"
    And the icontent "Questions Testing" should include page "E Essay" with question "E Helicopter" of type "essay"
    And the icontent "Questions Testing" should include page "SA Short Answer" with question "SA Short Answer" of type "shortanswer"
    And the icontent "Questions Testing" should include page "NU Numerical" with question "Numerical" of type "numerical"
    And the icontent "Questions Testing" should include page "VarNumeric" with question "Variable Numerical add div" of type "varnumeric"
    And the icontent "Questions Testing" should include page "Formulas" with question "Book keeping 1 v3" of type "formulas"
    And the icontent "Questions Testing" should include page "DragDrop ddwtos" with question "(tour stage 30) Q28 DDT Gradebook question" of type "ddwtos"
    And the icontent "Questions Testing" should include page "Gap Select" with question "GapFill Binary addition" of type "gapfill"
    And the icontent "Questions Testing" should include page "Stack" with question "text_6_odd_even" of type "stack"
    And the icontent "Questions Testing" should include page "PoodLL Sketch" with question "(tour stage 24) Q22 OM an OpenMark example" of type "poodllrecording"
