<?php
/**
 *
 * @copyright &copy; 2010 The Open University
 * @author a.j.forth@open.ac.uk
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package dataplus
 */
class sqlite3_db {
    private $conn;
    private $file_info;
    private $temp_db_path;
    private $temp_path;
    private $locked = false;

    /**
     * Create a PDO connection to an SQLite database (which is created in the file system if does not exist). 
     *  Sets the new_database variable if the database had to be created.
     *
     * @param int $db_id
     */     
    public function __construct($db_id, $lock = false){
        global $context, $CFG, $USER;
        
        $fs = get_file_storage();
            
        $this->fileinfo = array('component' => 'mod_dataplus',
                                'filearea' => 'dataplus',
                                'itemid' => $db_id,
                                'contextid' => $context->id,
                                'filepath' => '/',
                                'filename' => (string) $db_id .'.sqlite'); 

        if($lock){
        	if(!$this->database_lock()){
        		global $id;
                print_error(get_string('lockerror','dataplus'), $CFG->wwwroot.'/mod/dataplus/view.php?mode=view&amp;id=' . $id);
        	}
        }
        
        $this->temp_path = $CFG->dataroot . '/temp/dataplus/';
            
        if(!file_exists($this->temp_path)){
            mkdir($this->temp_path);
        }
            
        $this->temp_path .= $this->fileinfo['contextid'];

        if(!file_exists($this->temp_path)){
            mkdir($this->temp_path);
        }
        
        $this->temp_path .= '/'.$USER->id;

        if(!file_exists($this->temp_path)){
            mkdir($this->temp_path);
        }
        
        $this->temp_db_path = $this->temp_path . '/' . $this->get_db_file_name();

        $file = $fs->get_file($this->fileinfo['contextid'], 
                              $this->fileinfo['component'], 
                              $this->fileinfo['filearea'], 
                              $this->fileinfo['itemid'], 
                              $this->fileinfo['filepath'], 
                              $this->fileinfo['filename']);

        if(!empty($file)){
            $file->copy_content_to($this->temp_db_path);
        } 

        $this->conn = new PDO('sqlite:' . $this->temp_db_path);
            
        if(!$this->table_exists('columns')){
            $col_columns = $this->get_columns_table_column_details();
            $this->create_table("column",$col_columns);
        }                       
    }

        
    /*
     * copies the temporary database to the moodle file store and cleans up the temp directory
     */
    public function close(){
   		$this->database_unlock();

        $fs = get_file_storage();

        $file = $fs->get_file($this->fileinfo['contextid'], 
                              $this->fileinfo['component'], 
                              $this->fileinfo['filearea'], 
                              $this->fileinfo['itemid'], 
                              $this->fileinfo['filepath'], 
                              $this->fileinfo['filename']);

        if($file){
            $file->delete();
        }  
            
        if(file_exists($this->temp_db_path)){
            $fs->create_file_from_pathname($this->fileinfo,$this->temp_db_path);
            unlink($this->temp_db_path);
        }
    }
       
    /**
     * returns the filename of the database
     */
    public function get_db_file_name(){
        return $this->fileinfo['filename'];
    }


    /**
     * executes a SQL statement
     *
     * @param string $sql
     */ 
    private function execute_sql($sql){
        if (is_null($this->conn)) {
            return false;
        }

        $query_check = strtolower(substr($sql,0,6));

        if($query_check == 'select' || $query_check == 'pragma'){
            $result = &$this->conn->query($sql);

            if(empty($result)){
                return false;
            }

            return $result;
        }
        else {
            $result = &$this->conn->exec($sql);

            if(is_int($result)) {
                return true;
            }
            else {
                return false;
            }
        }
    }


    /**
     * creates a valid name for an object - must be 20 chars or less, start with a letter and only contain
     * alphanumeric characters based on a name given for use
     *
     * @param string $name
     */
    public function create_valid_object_name($name){
        $name = preg_replace('/(^[^a-zA-Z])/','L\1',$name);
           
        // this fixes an odd bug whereby naming a field 'link' was causing the app to sometimes behave as though two fields exist with the same name.
        if(strtolower($name) == 'link'){
            $name = 'link1234';
        }

        return substr(preg_replace('/[^a-zA-Z0-9]/','',$name),0,20);
    }


    /**
     * deletes the database from the file system
     *
     * @return unknown
     */
    public function delete_db(){
        $this->conn = null;

        $result = unlink($this->path . '/' .  $this->db_file_name);
           
        $this->path         = null;
        $this->db_file_name = null;

        return $result;
    }


    /**
     * A text file is used to ensure the database is locked, this returns it's path
     */
    private function get_lock_fileinfo(){
    	$fileinfo = $this->fileinfo;
    	
    	$fileinfo['itemid']   = 0;
    	$fileinfo['filename'] = 'lock.txt';
    	
        return $fileinfo;
    }


    /**
     * If the timestamp in lock.txt is greater than 60 seconds, set a time stamp and return true to indicate
     * the database is under control of this process.  If less than 60 seconds, then wait to until 60 seconds 
     * passes or it is released
     */
    private function database_lock(){
        $fileinfo = $this->get_lock_fileinfo();
        
        $fs = get_file_storage();

        if($file = $fs->get_file($fileinfo['contextid'], $fileinfo['component'], $fileinfo['filearea'], 
        $fileinfo['itemid'], $fileinfo['filepath'], $fileinfo['filename'])){
            $date = (int) $file->get_content();

            if($date > time()-60){
                while($date > time()-60){
                    sleep(5);
                    $date = (int) $file->get_content();
                }
            }
            
            $file->delete();
        }

        
        
        if($file = $fs->create_file_from_string($fileinfo,time())){
            $this->locked = true;
            return true;
        }
        else {
        	return false;
        }
    }


    /**
     * Used for setting the timestamp in lock.txt to 0 when an operation is complete.
     */
    private function database_unlock(){
        $fileinfo = $this->get_lock_fileinfo();
        
        $fs = get_file_storage();
    	
        $file = $fs->get_file($fileinfo['contextid'], $fileinfo['component'], $fileinfo['filearea'], 
        $fileinfo['itemid'], $fileinfo['filepath'], $fileinfo['filename']);
        
        if($file->delete()){
            $this->locked = false;
            return true;
        }
        else {
        	return false;
        }
    }


    /**
     * returns an array of the columns in the 'column' table suitable for use in table creation or queries
     *
     */
    protected function get_columns_table_column_details(){
        $columns = array();

        $columns[0]->name          = 'id';
        $columns[0]->type          = 'integer';
        $columns[0]->primary_key   = true;
        $columns[0]->autoincrement = true;
        $columns[0]->notnull       = true;

        $columns[1]->name = 'name';
        $columns[1]->type = 'text';

        $columns[2]->name = 'label';
        $columns[2]->type = 'text';

        $columns[3]->name = 'type';
        $columns[3]->type = 'text';

        $columns[4]->name = 'primary_key';
        $columns[4]->type = 'boolean';

        $columns[5]->name = 'autoincrement';
        $columns[5]->type = 'boolean';

        $columns[6]->name = 'not_null';
        $columns[6]->type = 'boolean';

        $columns[7]->name = 'table_name';
        $columns[7]->type = 'text';

        return $columns;
    }


    /**
     * returns a list of the names of columns in the 'column' table
     */
    public function get_columns_table_column_list(){
        $cols = $this->get_columns_table_column_details();

        $list = array();

        foreach ($cols as $col){
            $list[] = $col->name;
        }

        return $list;
    }

      
    /**
     * validates an SQLite database for use in DataPlus by checking the 'column' table exists
     */
    public function validate_database(){
        $column_exits = $this->table_exists('column');

        if($column_exits){
            return true;
        }

        return get_string('validate_table_column','dataplus');
    }


    /**
     * Handles any escapes, etc, needed in values.
     *
     * @param unknown_type $value
     * @return unknown
     */        
    public function prepare_value($value){
        $value = str_replace("'","''",$value);
           
        return $value;
    }
       
       
    /**
     * generate the SQL statement to create a table and execute it
     *
     * @param string $table_name
     * @param array $columns
     * @return unknown
     */
    public function create_table($table_name, $columns){
        $columns_sql = '';

        $table_name = $this->create_valid_object_name($table_name);

        foreach($columns as $column){
            if(!empty($columns_sql)){
                $columns_sql .= ',';
            }

            $columns_sql .= "\"{$column->name}\"";

            $type = $column->type;

            $columns_sql .= " {$type}";

            if(isset($column->primary_key) && $column->primary_key==true){
                $columns_sql .= " PRIMARY KEY";
            }

            if(isset($column->autoincrement) && $column->autoincrement == true){
                $columns_sql .= " AUTOINCREMENT";
            }

            if(isset($column->not_null) && $column->not_null==true){
                $columns_sql .= " NOT NULL";
            }

        }

        $sql = "CREATE TABLE \"{$table_name}\" ({$columns_sql})";

        $result = $this->execute_sql($sql);

        return $result;
    }

      
    /**
     * generate an SQL statement to drop a table and execute it
     *
     * @param string $table_name
     */
    public function drop_table($table_name) {
        $sql = "DROP TABLE \"{$table_name}\";";

        $result = $this->execute_sql($sql);
           
        return $result;
    }


    /**
     * return an array with all the details of the columns from a table.  Parameters can be specified to
     * restrict the columns returned.  These two functions exist because autoincrement info is not included
     * in PRAGMA table_info in SQLite
     *
     * @param string $table_name
     * @param array $org_parameters
     */
    public function list_table_columns($table_name,$org_parameters = array()){
        $columns = $this->get_columns_table_column_list();
           
        $parameters[0]->name     = 'table_name';
        $parameters[0]->value    = $table_name;
        $parameters[0]->operator = 'equals';

        if(sizeof($org_parameters)>0){
            $parameters[0]->sub = $org_parameters;
        }
           
        return $this->query_database('column',$columns,$parameters);
    }


    /**
     * return just the names of the columns in a table
     *
     * @param string $table_name
     */
    public function list_table_columns_names($table_name){
        $columns = $this->list_table_columns($table_name);

        foreach($columns as $column){
            $names[] = $column->name;
        }

        return $names;
    }


      /**
       * return an array with all the output of table_info for a table.  
       *
       * @param string $table_name
       */
       public function pragma_table($table_name){

           $records = $this->execute_sql("PRAGMA table_info({$table_name});");
           
           return $this->convert_pdo_to_array($records);
       }
    
    
    /**
     * get the details of one of the fields associated with a table column from the 'column' table
     *
     * @param string $table_name
     * @param string/int $id
     * @param string $field
     */
    public function get_column_field($table_name,$id,$field){
        $parameters[0]->name      = 'id';
        $parameters[0]->value     = $id;
        $parameters[0]->operator = 'equals';

        $col = $this->list_table_columns($table_name,$parameters);

        if(sizeof($col) == 0){
            return false;
        }

        return $col[0]->$field;
    }


    /**
     * get the name of a table column, as stored in the 'column' table, by it's id
     *
     * @param string $table_name
     * @param string/int $id
     */
    public function get_column_field_name($table_name,$id){
        return $this->get_column_field($table_name,$id,'name');
    }


    /**
     * get the details of an individual column, as stored in the 'column' table, by it's id
     *
     * @param string/int $id
     */
    public function get_column_details($id){
        $columns = $this->get_columns_table_column_list();

        $parameters = array();

        $parameters[0]->name  = 'id';
        $parameters[0]->value = $id;

        $result = $this->query_database('column',$columns,$parameters);

        return $result[0];
    }


    /**
     * check a column exists in table, according to the information stored in the 'column' table
     *
     * @param string $table_name
     * @param string $column_name
     */
    public function check_column_exists($table_name, $column_name) {
        $columns = $this->pragma_table($table_name);

        foreach($columns as $column){
            if($column->name == $column_name) {
                return true;
            }
        }

        return false;
    }


    /**
     * add a column to a table and supporting data to the 'column' table
     *
     * @param string $column_label
     * @param string $type
     */
    public function add_column($column_label, $type){
        $column_name = $this->create_valid_object_name($column_label);

        $result = $this->add_column_query("content",$column_name, $type);

        if($result === true){
            $fields = array();

            $columns[0]->name  = 'name';
            $columns[0]->value = $column_name;

            $columns[1]->name  = 'label';
            $columns[1]->value = $column_label;

            $columns[2]->name  = 'type';
            $columns[2]->value = $type;

            $columns[3]->name  = 'table_name';
            $columns[3]->value = 'content';

            $result = $this->insert_record("column",$columns);
        }
    
        return $column_name;
    }


    /**
     * generate and execute the SQL statement for adding a column
     *
     * @param string $table_name
     * @param string $column_name
     * @param string $column_type
     * @param boolean $autoincrement
     * @param boolean $primary_key
     * @param boolean $not_null
     */
    protected function add_column_query($table_name, $column_name, $column_type, $autoincrement = false, $primary_key = false, $not_null = false){
        $column_name = $this->create_valid_object_name($column_name);

        $column_exists = $this->check_column_exists($table_name, $column_name);

        if($column_exists){
            return "COLUMNEXISTS";
        }

        if(!empty($autoincrement)){
            $autoincrement = 'AUTOINCREMENT';
        }

        if(!empty($primary_key)){
            $primary_key = 'PRIMARY KEY';
        }

        if(!empty($not_null)){
            $primary_key = 'NOT NULL';
        }

        $sql = "ALTER TABLE \"{$table_name}\" ADD COLUMN \"{$column_name}\" {$column_type} {$primary_key} {$autoincrement} $not_null";

        $result = $this->execute_sql($sql);
           
        return $result;
    }


    /**
     * delete a column from a table
     *
     * @param string $table_name
     * @param int $column_id
     */
    public function delete_column($table_name,$column_id){
        $result = $this->delete_column_query($table_name, $column_id);

        if($result){
            $result = $this->delete_column_record($table_name, $column_id);
        }

        return $result;
    }


    /**
     * delete the record for a column from the 'column' table
     *
     * @param string $table_name
     * @param int $id
     */
    protected function delete_column_record($table_name, $id){
        $delete_params = array();

        $delete_params[0]->name     = 'id';
        $delete_params[0]->value    = $id;
        $delete_params[0]->operator = 'equals';

        $delete_params[1]->name     = 'table_name';
        $delete_params[1]->value    = $table_name;
        $delete_params[1]->operator = 'equals';

        return $this->delete_record('column',$delete_params);
    }


    /**
     * call all the functions for generating and executing SQL for deleting columns (note - there is no
     * easy way to delete a column in SQLite)
     *
     * @param string $table_name
     * @param int $column_id
     */
    protected function delete_column_query($table_name, $column_id){
        $columns = $this->list_table_columns($table_name);

        $i = 0;

        foreach ($columns as $column){
            if($column->id == $column_id){
                unset($columns[$i]);
                break;
            }

            $i++;
        }
           
        $temp_name = "deletebackup" . mt_rand(1,1000000);

        $results = array();

        $results[] = $this->create_table($temp_name, $columns);
        $results[] = $this->copy_table_data($table_name, $temp_name, $columns);
        $results[] = $this->drop_table($table_name);
        $results[] = $this->create_table($table_name, $columns);
        $results[] = $this->copy_table_data($temp_name, $table_name, $columns);
        $results[] = $this->drop_table($temp_name);


        foreach($results as $result){
            if($result !== true ){
                return $result;
            }
        }

        return true;
    }


    /**
     * alter a table column, including altering the record in the 'column' table
     *
     * @param string $table_name
     * @param obj $column_details
     */
    public function alter_column($table_name,$column_details){
        $update = array();

        $i = 0;

        $stored_column = $this->get_column_details($column_details->id);

        if($column_details->label !== $stored_column->label){
            $name  = $this->create_valid_object_name($column_details->label);
            $label = $column_details->label;

            $column_details->new_name = $name;

            $update[$i]->name  = 'label';
            $update[$i]->value = $label;
            $i++;

            $update[$i]->name  = 'name';
            $update[$i]->value = $name;
            $i++;
        }
        else {
            $column_details->new_name = $column_details->label;
        }

        if($column_details->type !== $stored_column->type){
            $update[$i]->name  = 'type';
            $update[$i]->value = $column_details->type;
        }

        $result = $this->alter_column_query($table_name,$column_details);

        if($result === "COLUMNEXISTS"  || $result === false) {
            return $result;
        }

        if(sizeof($update)==0){
            return 'NOTHINGTODO';
        }

        $parameters = array();

        $parameters[0]->name     = 'id';
        $parameters[0]->value    = $column_details->id;
        $parameters[0]->operator = 'equals';

        $parameters[1]->name     = 'table_name';
        $parameters[1]->value    = 'content';
        $parameters[1]->operator = 'equals';

        $result = $this->update_record('column',$update,$parameters);

        return $result;
    }


    /**
     * call all the functions for generating and executing SQL statements for altering a column 
     * (this being long and complicated due to SQLite not supporting the alteration of columns)
     *
     * @param string $table_name
     * @param string $column_details
     */
    protected function alter_column_query($table_name, $column_details){
        $new_column_exists = $this->check_column_exists($table_name, $column_details->new_name);

        if($new_column_exists){
            return "COLUMNEXISTS";
        }

        $old_columns = $this->list_table_columns($table_name);
        $new_columns = $this->list_table_columns($table_name);

        $i = 0;

        foreach ($new_columns as $new_column){
            if($new_column->id == $column_details->id){
                if(isset($column_details->new_name)){
                    $new_columns[$i]->name = $column_details->new_name;
                }

                if(isset($detail->type)){
                    $new_columns[$i]->type = $column_details->type;
                }
                break;
            }

            $i++;
        }

        $temp_name = 'renamebackup' . mt_rand(1,1000000);

        $results = array();

        $results[] = $this->create_table($temp_name, $old_columns);
        $results[] = $this->copy_table_data($table_name, $temp_name, $old_columns);
        $results[] = $this->drop_table($table_name);
        $results[] = $this->create_table($table_name, $new_columns);
        $results[] = $this->copy_table_data($temp_name, $table_name, $old_columns);
        $results[] = $this->drop_table($temp_name);

        foreach($results as $result){
            if($result === false ){
                return false;
            }
        }

        return true;
    }


    /**
     * insert a record to a table
     *
     * @param string $table_name
     * @param array $columns
     */
    public function insert_record($table_name,$columns){
        $columns_sql = '';
        $values_sql = '';

        foreach ($columns as $column){
            if (!empty($columns_sql)){
                $columns_sql .= ", ";
            }

            $columns_sql .= "\"{$column->name}\"";

            if (!empty($values_sql)){
                $values_sql .= ", ";
            }

            $value       = $this->prepare_value($column->value);
            $values_sql .= "'{$value}'";
        }

        $sql = "INSERT INTO \"{$table_name}\" ({$columns_sql}) VALUES ({$values_sql})";

        $result = $this->execute_sql($sql);

        return $result;
    }


    /**
     * copy all the data in all the specified columns from one table to another
     *
     * @param string $source_table
     * @param string $destination_table
     * @param array$columns
     */
    protected function copy_table_data($source_table, $destination_table, $columns){
        $columns_sql = '';

        foreach($columns as $column){
            if(!empty($columns_sql)){
                $columns_sql .= ', ';
            }

            $columns_sql .= "\"{$column->name}\"";
        }

        $sql = "INSERT INTO \"{$destination_table}\" SELECT {$columns_sql} FROM \"{$source_table}\";";

        $result = $this->execute_sql($sql);
           
        return $result;
    }


    /**
     * generates basic 'where' clauses for use in queries.  Subs can be used for parts to be contained in brackets
     *
     * @param array $parameters
     */
    protected function get_where_clause($parameters){
        $parameters_sql = '';

        foreach($parameters as $parameter){ 
            if(!empty($parameters_sql)){
                if(!isset($parameter->andor)){
                    $parameter->andor = 'AND';
                }
                $parameters_sql .= " {$parameter->andor} ";
            }
               
            if(isset($parameter->sub)){
                if(!empty($parameter->sub)){
                    $parameters_sql .= '(' . $this->get_where_clause($parameter->sub) . ')';
                }
                continue;
            }

            $value = $this->prepare_value($parameter->value);
            $name  = $parameter->name;
               
            if(!isset($parameter->operator) || $parameter->operator == 'contains'){
                $parameters_sql .= "\"{$name}\" LIKE '%{$value}%'";
            }
            else if($parameter->operator == 'equals'){
                $parameters_sql .= "\"{$name}\" = '{$value}'";
            }
            else if($parameter->operator == 'notequal'){
                $parameters_sql .= "\"{$name}\" != '{$value}'";
            }
            else if($parameter->operator == 'lessthan'){
                $parameters_sql .= "\"{$name}\" < {$value}";
            }
            else if($parameter->operator == 'greaterthan'){
                $parameters_sql .= "\"{$name}\" > {$value}";
            }
        }

        return $parameters_sql;
    }

    /**
     * Delete the record/s from a table.  If no parameters are set, all the data is deleted
     *
     * @param string $table_name
     * @param array $parameters
     */
    public function delete_record($table_name,$parameters = null){
        $sql = "DELETE FROM \"{$table_name}\"";

        if(!is_null($parameters)){
            $parameters_sql = $this->get_where_clause($parameters);
            $sql .= " WHERE {$parameters_sql}";
        }

        $result = $this->execute_sql($sql);

        return $result;
    }


    /**
     * update record/s.  If no parameters are set, all records will be updated
     *
     * @param string$table_name
     * @param array $columns
     * @param array $parameters
     */
    public function update_record($table_name,$columns,$parameters = array()){
        $columns_sql = '';

        foreach ($columns as $column){
            if(!empty($columns_sql)){
                $columns_sql .= ', ';
            }

            $value = $this->prepare_value($column->value);
               
            $columns_sql .= "\"{$column->name}\" = '{$value}'";
        }

        $sql = "UPDATE \"{$table_name}\" SET {$columns_sql}";

        $parameters_sql = $this->get_where_clause($parameters);
           
        if(!empty($parameters_sql)){
            $sql .= " WHERE {$parameters_sql}";
        }
           
        $result = $this->execute_sql($sql);

        return $result;
    }

    
    /**
     * Converts a PDO object to an array
     * 
     * @param obj $pdo
     */
    private function convert_pdo_to_array($pdo){
        $details = array();

        $i = 0;

        while ($record = $pdo->fetch(PDO::FETCH_ASSOC)) {
            foreach ($record as $name=>$value){
                $details[$i]->$name = $value;
            }

            $i++;
        }
            
        return $details;
    }

    /**
     * query the a table in the database.  If no parameters or limit is set, all records are returned.
     *
     * @param string $table_name
     * @param array $columns
     * @param array $parameters
     * @param array $limit
     * @param array $order
     */
    public function query_database($table_name,$columns,$parameters = null, $limit = null, $order = null){
        $columns_sql = '';

        foreach ($columns as $column) {
            if(!empty($columns_sql)){
                $columns_sql .= ', ';
            }

            $columns_sql .= "\"{$column}\"";
        }

        $sql = "SELECT {$columns_sql} FROM \"{$table_name}\"";

        if(!empty($parameters)){
            $parameters_sql = $this->get_where_clause($parameters);
            $sql .= " WHERE {$parameters_sql}";
        }

        if(!empty($order)){
            $sql .= " ORDER BY";

            $i = 0;

            foreach($order as $o){
                if(isset($o->sort) && $o->sort == 'DESC'){
                    $sql .= " UPPER(\"{$o->name}\") DESC";
                }
                else {
                    $sql .= " UPPER(\"{$o->name}\")";
                }

                $i++;

                if($i<sizeof($order)){
                    $sql .= ',';
                }
            }
        }

        if(!empty($limit)){
            $sql .= " LIMIT {$limit['start']},{$limit['number']}";
        }

        $records = $this->execute_sql($sql);

        if(!$records){
            return false;
        }

        return $this->convert_pdo_to_array($records);
    }


    /**
     * return a single result to a query.  If the query returns more than one result, the first is returned.
     *
     * @param string $table_name
     * @param array $columns
     * @param array $parameters
     * @param array $order
     */
    public function query_database_single($table_name,$columns,$parameters = null, $order = null){
        $limit['start']  = 0;
        $limit['number'] = 1;

        $results = $this->query_database($table_name,$columns,$parameters,$limit,$order);

        if(empty($results)){
            return false;
        }

        return $results[0];
    }


    /**
     * count the number of results in a database query
     *
     * @param string $table_name
     * @param array $parameters
     */
    public function count_database_query($table_name,$parameters = null){
        $sql = "SELECT COUNT(*) AS count FROM \"{$table_name}\"";

        if(!empty($parameters)){
            $parameters_sql = $this->get_where_clause($parameters);
            $sql .= " WHERE {$parameters_sql}";
        }

        $result = $this->execute_sql($sql);

        if(!$result){
            return false;
        }

        foreach($result as $r){
            return (int) $r["count"];
        }
    }


    /**
     * check a table with a given name exists
     *
     * @param string $table_name
     */
    public function table_exists($table_name){
        $parameters[0]->name  = 'tbl_name';
        $parameters[0]->value = $table_name;
        $parameters[0]->operator = 'equals';

        $count = $this->count_database_query('sqlite_master',$parameters);

        if($count == 1){
            return true;
        }

        return false;
    }
       
       
    /**
     * get columns information for a table
     *
     * @param string $table_name
     */
    protected function get_table_info($table_name){
        $sql = "PRAGMA table_info('{$table_name}')";

        $info = $this->execute_sql($sql);

        if(!$info){
            return false;
        }

        $details = array();

        $i = 0;

        while ($record = $info->fetch(PDO::FETCH_ASSOC)) {
            foreach ($record as $name=>$value){
                $details[$i]->$name = $value;
            }

            $i++;
        }
        
        return $details;
    }
       
       
    /**
     * get information on a particular column from a table
     *
     * @param string $table_name
     * @param string $col_name
     */
    protected function get_column_info($table_name, $col_name){
        $cols = $this->get_table_info($table_name);
            
        foreach($cols as $col){
            if($col->name == $col_name){
                return $col;
            }
        }
            
        return false;
    }
}