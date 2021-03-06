<?php

namespace BCCHR\DataEntryTriggerBuilder;

use REDCap;
use Project;

class DataEntryTriggerBuilder extends \ExternalModules\AbstractExternalModule 
{
    /**
     * Replaces given text with replacement.
     * 
     * @access private
     * @param String $text          The text to replace.
     * @param String $replacement   The replacement text.
     * @return String A string with the replaced text.
     */
    private function replaceStrings($text, $replacement)
    {
        preg_match_all("/'/", $text, $quotes, PREG_OFFSET_CAPTURE);
        $quotes = $quotes[0];
        if (sizeof($quotes) % 2 === 0)
        {
            $i = 0;
            $to_replace = array();
            while ($i < sizeof($quotes))
            {
                $to_replace[] = substr($text, $quotes[$i][1], $quotes[$i + 1][1] - $quotes[$i][1] + 1);
                $i = $i + 2;
            }

            $text = str_replace($to_replace, $replacement, $text);
        }
        return $text;
    }

    /**
     * Parses a syntax string into blocks.
     * 
     * @access private
     * @param String $syntax    The syntax to parse.
     * @return Array            An array of blocks that make up the syntax passed.
     */
    private function getSyntaxParts($syntax)
    {
        $syntax = str_replace(array("['", "']"), array("[", "]"), $syntax);
        $syntax = $this->replaceStrings(trim($syntax), "''");         //Replace strings with ''

        $parts = array();
        $previous = array();

        $i = 0;
        while($i < strlen($syntax))
        {
            $char = $syntax[$i];
            switch($char)
            {
                case ",":
                case "(":
                case ")":
                case "]":
                    $part = trim(implode("", $previous));
                    $previous = array();
                    if ($part !== "")
                    {
                        $parts[] = $part;
                    }
                    $parts[] = $char;
                    $i++;
                    break;
                case "[":
                    $part = trim(implode("", $previous));
                    if ($part !== "")
                    {
                        $parts[] = $part;
                    }
                    $parts[] = $char;
                    $previous = array();
                    $i++;
                    break;
                case " ":
                    $part = trim(implode("", $previous));
                    $previous = array();
                    if ($part !== "")
                    {
                        $parts[] = $part;
                    }
                    $i++;
                    break;
                default:
                    $previous[] = $char;
                    if ($i == strlen($syntax) - 1)
                    {
                        $part = trim(implode("", $previous));
                        if ($part !== "")
                        {
                            $parts[] = $part;
                        }
                    }
                    $i++;
                    break;
            }
        }

        return $parts;
    }

    /**
     * Checks whether a field exists within a project.
     * 
     * @param String $var       The field to validate
     * @param String $pid       The project id the field supposedly belongs to. Use current project if null.
     * @return Boolean          true if field exists, false otherwise.
     */
    public function isValidField($var, $pid = null)
    {
        $var = trim($var, "'");
        
        if ($pid != null) {
            $data_dictionary = REDCap::getDataDictionary($pid, 'array');
        }
        else {
            $data_dictionary = REDCap::getDataDictionary('array');
        }

        $external_fields = array();
        $instruments = array_unique(array_column($data_dictionary, "form_name"));
        foreach ($instruments as $unique_name)
        {   
            $external_fields[] = "{$unique_name}_complete";
        }
        
        return in_array($var, $external_fields) || !empty($data_dictionary[$var]);
    }

    /**
     * Checks whether a event exists within a project.
     * 
     * @param String $var       The event to validate
     * @param String $pid       The project id the event supposedly belongs to. Use current project if null.
     * @return Boolean          true if event exists, false otherwise.
     */
    public function isValidEvent($var, $pid = null)
    {
        $var = trim($var, "'");
        $Proj = new Project($pid);
        $events = array_values($Proj->getUniqueEventNames());
        return in_array($var, $events);
    }

    /**
     * Checks whether a instrument exists within a project.
     * 
     * @param String $var       The instrument to validate
     * @param String $pid       The project id the instrument supposedly belongs to. Use current project if null.
     * @return Boolean          true if instrument exists, false otherwise.
     */
    public function isValidInstrument($var, $pid = null)
    {
        $var = trim($var, "'");
        
        if ($pid != null) {
            $data_dictionary = REDCap::getDataDictionary($pid, 'array');
        }
        else {
            $data_dictionary = REDCap::getDataDictionary('array');
        }

        $instruments = array_unique(array_column($data_dictionary, "form_name"));
        
        return in_array($var, $instruments);
    }

    /**
     * Validate syntax.
     * 
     * @access private
     * @see Template::getSyntaxParts()  For retreiving blocks of syntax from the given syntax string.
     * @param String $syntax            The syntax to validate.
     * @since 1.0
     * @return Array                    An array of errors.
     */
    public function validateSyntax($syntax)
    {
        $errors = array();

        $logical_operators =  array("==", "<>", "!=", ">", "<", ">=", ">=", "<=", "<=", "||", "&&", "=");
        
        $parts = $this->getSyntaxParts($syntax);

        $opening_squares = array_keys($parts, "[");
        $closing_squares = array_keys($parts, "]");

        $opening_parenthesis = array_keys($parts, "(");
        $closing_parenthesis = array_keys($parts, ")");

        // Check symmetry of ()
        if (sizeof($opening_parenthesis) != sizeof($closing_parenthesis))
        {
            $errors[] = "<b>ERROR</b>Odd number of parenthesis (. You've either added an extra parenthesis, or forgot to close one.";
        }

        // Check symmetry of []
        if (sizeof($opening_squares) != sizeof($closing_squares))
        {
            $errors[] = "Odd number of square brackets [. You've either added an extra bracket, or forgot to close one.";
        }

        foreach($parts as $index => $part)
        {
            switch ($part) {
                case "(":
                    $previous = $parts[$index - 1];
                    $next_part = $parts[$index + 1];
                
                    if (($next_part !== "(" 
                        && $next_part !== ")" 
                        && $next_part !== "["))
                    {
                        $errors[] = "Invalid <strong>$next_part</strong> after <strong>(</strong>.";
                    }
                    break;
                case ")":
                    // Must have either a ) or logical operator after, if not the last part of syntax
                    if ($index != sizeof($parts) - 1)
                    {
                        $next_part = $parts[$index + 1];
                        if ($next_part !== ")" && !in_array($next_part, $logical_operators))
                        {
                            $errors[] = "Invalid <strong>$next_part</strong> after <strong>)</strong>.";
                        }
                    }
                    break;
                case "==":
                case "<>": 
                case "!=":
                case ">":
                case "<":
                case ">=":
                case ">=":
                case "<=":
                case "<=":
                case "=":
                    if ($index == 0)
                    {
                        $errors[] = "Cannot have a comparison operator <strong>$part</strong> as the first part in syntax.";
                    }
                    else if ($index != sizeof($parts) - 1)
                    {
                        $previous = $parts[$index - 2];
                        $next_part = $parts[$index + 1];

                        if (in_array($previous, $logical_operators) && $previous !== "or" && $previous !== "and")
                        {
                            $errors[] = "Invalid <strong>$part</strong>. You cannot chain comparison operators together, you must use an <strong>and</strong> or an <strong>or</strong>";
                        }

                        if (!empty($next_part) 
                            && !is_numeric($next_part)
                            && $next_part[0] != "'" 
                            && $next_part[0] != "\""
                            && $next_part[strlen($next_part) - 1] != "'" 
                            && $next_part[strlen($next_part) - 1] != "\"")
                        {
                            $errors[] = "Invalid <strong>$next_part</strong> after <strong>$part</strong>.";
                        }
                    }
                    else
                    {
                        $errors[] = "Cannot have a comparison operator <strong>$part</strong> as the last part in syntax.";
                    }
                    break;
                case "||":
                case "&&":
                    if ($index == 0)
                    {
                        $errors[] = "Cannot have a logical operator <strong>$part</strong> as the first part in syntax.";
                    }
                    else if ($index != sizeof($parts) - 1)
                    {
                        $next_part = $parts[$index + 1];
                        if (!empty($next_part) 
                            && $next_part !== "(" 
                            && $next_part !== "[")
                        {
                            $errors[] = "Invalid <strong>$next_part</strong> after <strong>$part</strong>.";
                        }
                    }
                    else
                    {
                        $errors[] = "Cannot have a logical operator <strong>$part</strong> as the last part in syntax.";
                    }
                    break;
                case "[":
                    break;
                case "]":
                    // Must have either a logical operator or ) or [ after, if not last item in syntax
                    if ($index != sizeof($parts) - 1)
                    {
                        $previous_2 = $parts[$index - 2];
                        $next_part = $parts[$index + 1];

                        if ($previous_2 !== "[")
                        {
                            $errors[] = "Unclosed or empty <strong>]</strong> bracket.";
                        }

                        if ($next_part !== ")" 
                            && $next_part !== "["
                            && !in_array($next_part, $logical_operators))
                        {
                            $errors[] = "Invalid <strong>'$next_part'</strong> after <strong>$part</strong>.";
                        }
                    }
                    break;
                default:
                    // Check if it's a field or event
                    if ($part[0] != "'" && 
                        $part[0] != "\"" && 
                        $part[strlen($part) - 1] != "'" && 
                        $part[strlen($part) - 1] != "\"" &&
                        !is_numeric($part) && 
                        !empty($part) && 
                        ($this->isValidField($part) == false && $this->isValidEvent($part) == false))
                    {
                        $errors[] = "<strong>$part</strong> is not a valid event/field in this project";
                    }
                    break;
            }
        }

        return $errors;
    }
    
    /**
     * Retrieve the following for all REDCap projects: ID, & title
     * 
     * @return Array    An array of rows pulled from the database, each containing a project's information.
     */
    public function getProjects()
    {
        $sql = "select project_id, app_title from redcap_projects";
        if ($query_result = $this->query($sql))
        {
            while($row = db_fetch_assoc($query_result))
            {
                $projects[] = $row;
            }
            $query_result->close();
        }
        return $projects;
    }

    /**
     * Retrieves a project's fields
     * 
     * @param String $pid   A project's id in REDCap.
     * @return String       A JSON encoded string that contains all the instruments and fields for a project. 
     */
    public function retrieveProjectMetadata($pid)
    {
        if (!empty($pid))
        {
            $metadata = REDCap::getDataDictionary($pid, "array");
            $instruments = array_unique(array_column($metadata, "form_name"));
            $Proj = new Project($pid);
            $events = array_values($Proj->getUniqueEventNames());
            $isLongitudinal = $Proj->longitudinal;
            /**
             * We can pipe over any data except descriptive fields. 
             * 
             * NOTE: For calculation fields only the raw data can be imported/exported.
             */
            foreach($metadata as $field_name => $data)
            {
                if ($data["field_type"] != "descriptive" && $data["field_type"] != "calc")
                {
                    $fields[] = $field_name;
                }
            }

            /**
             * Add form completion status fields to push
             */
            foreach($instruments as $instrument)
            {
                $fields[] = $instrument . "_complete";
            }

            return ["fields" => $fields, "events" => $events, "isLongitudinal" => $isLongitudinal];
        }
        return FALSE;
    }

    /**
     * Parses a String of branching logic into blocks of logic syntax.
     * Assumes valid REDCap Logic syntax in trigger.
     * 
     * @param String $trigger_cond   A String of REDCap branching logic.
     * @return Array    An array of syntax blocks representing the given branching logic String.
     */
    private function parseCondition($trigger_cond)
    {
        $pos = strpos($trigger_cond, "(");

        /**
         * If brackets are at the beginning of the condition then split on first
         * && after them. If there are no && then split on the first ||.
         */
        if ($pos === 0)
        {
            for($i = 0; $i < strlen($trigger_cond); $i++)
            {
                if ($trigger_cond[$i] == "(")
                {
                    $opening_brackets[] = $i;
                }
                else if ($trigger_cond[$i] == ")")
                {
                    array_pop($opening_brackets);
                    if (empty($opening_brackets))
                    {
                        $closing_offset = $i;
                        break;
                    }
                }
                $closing_offset = -1;
            }

            if ($closing_offset == strlen($trigger_cond) - 1)
            {
                $left_cond = substr($trigger_cond, 1);
                $left_cond = substr($left_cond, 0, -1);

                return [
                    "left_branch" => $left_cond, 
                    "operand" => "", 
                    "right_branch" => ""
                ];
            }
            else
            {
                $remainder = substr($trigger_cond, $closing_offset+2);
            }
        }
        else if ($pos > 0)
        {
            $remainder = substr($trigger_cond, 0, $pos);
        }
        else if ($pos === FALSE)
        {
            $remainder = $trigger_cond;
        }

        $left_cond = "";
        $operator = "";
        $right_cond = "";

        if (preg_match("/\s*(&&)/", $remainder, $operators, PREG_OFFSET_CAPTURE) === 1 || 
            preg_match("/\s*(\|\|)/", $remainder, $operators, PREG_OFFSET_CAPTURE) === 1)
        {
            $relational_offset = $operators[0][1];
            $operator = $operators[0][0];

            if ($pos > 0 || $pos === FALSE)
            {
                $offset = $relational_offset + 1;
            }
            else if ($pos === 0)
            {
                $offset = $closing_offset + $relational_offset + 2;
            }

            $left_cond = trim(substr($trigger_cond, 0, $offset));
            $right_cond = trim(substr($trigger_cond, $offset + strlen($operator)));
            $operator = trim($operator);

            return [
                "left_branch" => $left_cond, 
                "operand" => $operator, 
                "right_branch" => $right_cond
            ];
        }
        else
        {
            return false;
        }
    }

    /**
     * Validates the given trigger.
     * Assumes valid REDCap Logic syntax in trigger.
     * 
     * @return Boolean
     */
    private function processTrigger($record_data, $trigger)
    {
        $logic_operators = array("==", "=", "<>", "!=", ">", "<", ">=", ">=", "<=");

        $tokens = $this->parseCondition($trigger);

        /**
         * If there's no relational operators, then split condition on 
         * logical operator and process.
         */
        if ($tokens === FALSE)
        {
            $blocks = preg_split("/(==|=|<>|!=|>|<|>=|>=|<=)/", $trigger, -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);
            
            if (!REDCap::isLongitudinal())
            {
                $field = trim($blocks[0], " []'\"()");
                $record_data = $record_data[0];
            }
            else
            {
                $split_pos = strpos($blocks[0], "][");

                $event = substr($blocks[0], 0, $split_pos);
                $field = substr($blocks[0], $split_pos+1);

                $event = trim($event, " []'\"()");
                $field = trim($field, " []'\"()");

                $event_key = array_search($event, array_column($record_data, "redcap_event_name"));
                $record_data = $record_data[$event_key];
            }

            $operator = trim($blocks[1]);
            $value = trim($blocks[2], " '\")");

            switch ($operator)
            {
                case "=":
                case "==":
                    return $record_data[$field] == $value;
                break;
                case "<>":
                case "!=":
                    return $record_data[$field] <> $value;
                break;
                case ">":
                    return $record_data[$field] > $value;
                break;
                case "<":
                    return $record_data[$field] < $value;
                break;
                case ">=":
                    return $record_data[$field] >= $value;
                break;
                case "<=":
                    return $record_data[$field] <= $value;
                break;
            }
        }
        /**
         * Split the condition, if there are relational operators,
         * and process left and right sides of argument on their own.
         * && takes priority
         */
        else if ($tokens["operand"] == "&&")
        {
            return $this->processTrigger($record_data, $tokens["left_branch"]) && $this->processTrigger($record_data, $tokens["right_branch"]);
        }
        else if ($tokens["operand"] == "||")
        {
            return $this->processTrigger($record_data, $tokens["left_branch"]) || $this->processTrigger($record_data, $tokens["right_branch"]);
        }
        else
        {
            return $this->processTrigger($record_data, $tokens["left_branch"]);
        }
        return true;
    }

    /**
     * REDCap hook is called immediately after a record is saved. Will retrieve the DET settings,
     * & import data according to DET.
     */
    public function redcap_save_record($project_id, $record, $instrument, $event_id, $group_id, $survey_hash, $response_id, $repeat_instance)
    {
        if ($project_id == $this->getProjectId())
        {
            $settings = json_decode($this->getProjectSetting("det_settings"), true);

            // Get DET settings
            $dest_project = $settings["dest-project"];
            $create_record_trigger = $settings["create-record-cond"];
            $link_source_event = $settings["linkSourceEvent"];
            $link_source = $settings["linkSource"];
            $link_dest_event = $settings["linkDestEvent"];
            $link_dest_field = $settings["linkDest"];
            $triggers = $settings["triggers"];
            $piping_source_events = $settings["pipingSourceEvents"];
            $piping_dest_events = $settings["pipingDestEvents"];
            $piping_source_fields = $settings["pipingSourceFields"];
            $piping_dest_fields = $settings["pipingDestFields"];
            $set_dest_events = $settings["setDestEvents"];
            $set_dest_fields = $settings["setDestFields"];
            $set_dest_fields_values = $settings["setDestFieldsValues"];
            $source_instruments_events = $settings["sourceInstrEvents"];
            $source_instruments = $settings["sourceInstr"];
            $overwrite_data = $settings["overwrite-data"];
            
            // Get current record data
            $record_data = json_decode(REDCap::getData("json", $record), true);

            foreach($triggers as $index => $trigger)
            {
                if ($this->processTrigger($record_data, $trigger))
                {
                    $trigger_source_fields = $piping_source_fields[$index];
                    $trigger_dest_fields = $piping_dest_fields[$index];
                    $trigger_source_events = $piping_source_events[$index];
                    $trigger_dest_events = $piping_dest_events[$index];

                    foreach($trigger_dest_fields as $i => $dest_field)
                    {
                        if (!empty($trigger_source_events[$i]))
                        {
                            $source_event = $trigger_source_events[$i];
                            $key = array_search($source_event, array_column($record_data, "redcap_event_name"));
                            $data = $record_data[$key];
                        }
                        else
                        {
                            $data = $record_data[0]; // Takes data from first event
                        }

                        if (!empty($trigger_dest_events[$i]))
                        {
                            $dest_event = $trigger_dest_events[$i];
                        }
                        else
                        {
                            $dest_event = "event_1_arm_1"; // Assume classic project and use event_1_arm_1
                        }
                        
                        if (empty($dest_record_data[$dest_event])) // Create entry for event if it doesn't already exist.
                        {
                            $event_data = ["redcap_event_name" => $dest_event];
                        }
                        else
                        {
                            $event_data = $dest_record_data[$dest_event];
                        }

                        $source_field = $trigger_source_fields[$i];
                        $event_data[$dest_field] = $data[$source_field];
                        $dest_record_data[$dest_event] = $event_data;
                    }

                    $trigger_dest_fields = $set_dest_fields[$index];
                    $trigger_dest_values = $set_dest_fields_values[$index];
                    $trigger_dest_events = $set_dest_events[$index];
                    foreach($trigger_dest_fields as $i => $dest_field)
                    {
                        if (!empty($trigger_dest_events[$i]))
                        {
                            $dest_event = $trigger_dest_events[$i];
                        }
                        else
                        {
                            $dest_event = "event_1_arm_1";
                        }

                        if (empty($dest_record_data[$dest_event]))
                        {
                            $event_data = ["redcap_event_name" => $dest_event];
                        }
                        else
                        {
                            $event_data = $dest_record_data[$dest_event];
                        }

                        $event_data[$dest_field] = $trigger_dest_values[$i];
                        $dest_record_data[$dest_event] = $event_data;
                    }

                    $trigger_source_instruments = $source_instruments[$index];
                    $trigger_source_instruments_events = $source_instruments_events[$index];
                    foreach($trigger_source_instruments as $i => $source_instrument)
                    {
                        if (!empty($trigger_source_instruments_events[$i]))
                        {
                            $event = $trigger_source_instruments_events[$i];
                        }
                        else
                        {
                            $event = "event_1_arm_1";
                        }

                        if (empty($dest_record_data[$event]))
                        {
                            $event_data = ["redcap_event_name" => $event];
                        }
                        else
                        {
                            $event_data = $dest_record_data[$event];
                        }

                        // Fields are returned in the order they are in the REDCap project
                        $source_instrument_fields = REDCap::getFieldNames($source_instrument);
                        $source_instrument_data = json_decode(REDCap::getData("json", $record, $source_instrument_fields, $event), true)[0];

                        $event_data = $event_data + $source_instrument_data;
                        $dest_record_data[$event] = $event_data;
                    }
                }
            }

            if (!empty($dest_record_data)) {
                // Check if the linking id field is the same as the record id field.
                $dest_record_id = $this->framework->getRecordIdField($dest_project);
                if ($dest_record_id != $link_dest_field)
                {
                    // Check for existing record, otherwise create a new one. Assume linking ID is unique
                    if (!empty($link_source_event))
                    {
                        $key = array_search($link_source_event, array_column($record_data, "redcap_event_name"));
                    }
                    else
                    {
                        $key = "event_1_arm_1";
                    }

                    $data = $record_data[$key];
                    $link_dest_value = $data[$link_source];

                    // Set linking id
                    if (!empty($link_dest_event))	
                    {	
                        $dest_record_data[$link_dest_event][$link_dest_field] = $link_dest_value;	
                    }	
                    else	
                    {	
                        $dest_record_data["event_1_arm_1"][$link_dest_field] = $link_dest_value;	
                    }

                    // Retrieve record id
                    $existing_record = REDCap::getData($dest_project, "json", null, $dest_record_id, $link_dest_event, null, false, false, false, "[$link_dest_field] = $link_dest_value");
                    $existing_record = json_decode($existing_record, true);
                    if (sizeof($existing_record) == 0)
                    {
                        $dest_record = $this->framework->addAutoNumberedRecord($dest_project);
                    }
                    else
                    {
                        $dest_record = $existing_record[0][$dest_record_id];
                    }
                }
                else
                {
                    $dest_record = $record_data[0][$link_source];
                }
                
                // Set record_id
                foreach ($dest_record_data as $i => $data)
                {
                    $dest_record_data[$i][$dest_record_id] = $dest_record;
                }

                $dest_record_data = array_values($dest_record_data); // Don't need the keys to push, only the values.
            }
            
            if (!empty($dest_record_data))
            {
                // Save DET data in destination project;
                $save_response = REDCap::saveData($dest_project, "json", json_encode($dest_record_data), $overwrite_data);

                if (!empty($save_response["errors"]))
                {
                    REDCap::logEvent("DET: Errors", json_encode($save_response["errors"]), null, $record, $event_id, $project_id);
                }
                else
                {
                    REDCap::logEvent("DET: Ran successfully", "Data was successfully imported from project $project_id to project $dest_project", null, $record, $event_id, $project_id);
                }

                if (!empty($save_response["warnings"]))
                {
                    REDCap::logEvent("DET: Ran sucessfully with Warnings", json_encode($save_response["warnings"]), null, $record, $event_id, $project_id);
                }

                if (!empty($save_response["ids"]))
                {
                    REDCap::logEvent("DET: Modified/Saved the following records", json_encode($save_response["ids"]), null, null, null, $dest_project);
                }
            }
        }
    }

    /**
     * Function called by external module that checks whether the user has permissions to use the module.
     * Only returns the link if the user has design privileges.
     * 
     * @param String $project_id    Project ID of current REDCap project.
     * @param String $link          Link that redirects to external module.
     * @return NULL Return null if the user doesn't have permissions to use the module. 
     * @return String Return link to module if the user has permissions to use it. 
     */
    public function redcap_module_link_check_display($project_id, $link)
    {
        if (SUPER_USER)
        {
            return $link;
        }
        else
        {
            return null;
        }
    }
}