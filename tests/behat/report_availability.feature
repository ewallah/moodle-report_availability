@report @report_availability @javascript
Feature: Availability report
  In order to collect availability
  As an teacher
  I need to be able to see availability report

  Background:
    Given the following "courses" exist:
      | fullname | shortname | enablecompletion | groupmode |
      | Course 1 | ENPRO001  | 1                | 1         |
    And the following "users" exist:
      | username | firstname | lastname | institution |
      | user1    | Username  | 1        | IPS         |
      | user2    | Username  | 2        | CVO         |
      | user3    | Username  | 3        | ITN         |
      | teacher  | Teacher   | 1        | EWA         |
      | other    | Teacher   | 2        | IPS         |
      | manager  | Manager   | 1        | EHS         |
    And the following "system role assigns" exist:
      | user    | course   | role    |
      | manager | ENPRO001 | manager |
    And the following "course enrolments" exist:
      | user    | course   | role           |
      | user1   | ENPRO001 | student        |
      | user2   | ENPRO001 | student        |
      | user3   | ENPRO001 | student        |
      | teacher | ENPRO001 | editingteacher |
      | other   | ENPRO001 | editingteacher |
    And the following "groups" exist:
      | name    | course   | idnumber |
      | Group A | ENPRO001 | G1       |
      | Group B | ENPRO001 | G2       |
    And the following "group members" exist:
      | user      | group |
      | user1     | G1    |
      | user3     | G1    |
      | teacher   | G1    |
      | user2     | G2    |
      | user3     | G2    |
      | other     | G2    |
      | manager   | G2    |
    And the following config values are set as admin:
      | grade_item_advanced | hiddenuntil |
    And the following "permission overrides" exist:
      | capability                  | permission | role           | contextlevel | reference |
      | moodle/site:accessallgroups | Prevent    | editingteacher | Course       | ENPRO001  |
    And the following "question categories" exist:
      | contextlevel | reference | name           |
      | Course       | ENPRO001  | Test questions |
    And the following "questions" exist:
      | questioncategory | qtype     | name           | questiontext              |
      | Test questions   | truefalse | First question | Answer the first question |
    And the following "activities" exist:
      | activity           | name         | intro   | course   | idnumber    | section | completion | completionview |
      | page               | page1        | Test l  | ENPRO001 | page1       | 1       | 1          | 1              |
    And the following "activities" exist:
      | activity | name  | course   | idnumber | attempts | gradepass | completion | completionusegrade | completionpassgrade | completionattemptsexhausted |
      | quiz     | quiz1 | ENPRO001 | pre      | 2        | 5.00      | 2          | 1                  | 1                   | 1                           |
      | quiz     | quiz2 | ENPRO001 | post     | 1        | 5.00      | 2          | 1                  | 1                   | 1                           |
    And quiz "quiz1" contains the following questions:
      | question       | page |
      | First question | 1    |
    And user "user1" has attempted "quiz1" with responses:
      | slot | response |
      |   1  | False    |
    And quiz "quiz2" contains the following questions:
      | question       | page |
      | First question | 1    |
    And user "user1" has attempted "quiz2" with responses:
      | slot | response |
      |   1  | False    |

  Scenario: Manager can see report availability
    Given I am on the "page1" "page activity" page logged in as user1
    And I am on the "quiz1" "quiz activity" page
    And I log out
    And I log in as "manager"
    And I am on "Course 1" course homepage with editing mode on
    And I navigate to "Reports > Availability" in current page administration
    Then I should see "Username 1"
    And I should see "Username 2"
    And I should see "Group A"
    And I should see "Group B"
    And I select "Group A" from the "group" singleselect
    And I should see "Username 1"
    And I should see "Username 3"
    But I should not see "Username 2"

  Scenario: Teachers can see availability reports
    When I am on the "page1" "page activity" page logged in as user1
    And I am on the "quiz1" "quiz activity" page logged in as user1
    And I log out
    And I log in as "teacher"
    And I am on "Course 1" course homepage
    And I navigate to "Reports > Availability" in current page administration
    Then I should see "Username 1"
    And I should see "Teacher 1"
    And I should not see "Username 2"
    And I should not see "Teacher 2"

  Scenario: Other teachers can see availability reports
    When I am on the "page1" "page activity" page logged in as user1
    And I am on the "quiz1" "quiz activity" page logged in as user1
    And I log out
    And I log in as "other"
    And I am on "Course 1" course homepage
    And I navigate to "Reports > Availability" in current page administration
    Then I should not see "Username 1"
    And I should not see "Teacher 1"
    And I should see "Teacher 2"
    And I should see "Username 2"

  Scenario: Different groups in availability
    When user "user3" has attempted "quiz1" with responses:
      | slot | response |
      |   1  | True     |
    And user "user3" has attempted "quiz2" with responses:
      | slot | response |
      |   1  | True     |
    And I log in as "teacher"
    And I am on "Course 1" course homepage
    And I navigate to "Reports > Availability" in current page administration
    Then I should see "Username 3"
    And I should see "Username 1"
    And I should not see "Username 2"
    And I should not see "Teacher 2"
