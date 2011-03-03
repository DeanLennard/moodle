<?php
/**
 *
 * @copyright &copy; 2010 The Open University
 * @author a.j.forth@open.ac.uk
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package dataplus
 */

class dataplus_file_helper {
    private $fileinfo;
    private $image_fileinfo;
    private $file_fileinfo;
    private $zip_fileinfo;
    private $temp_path;

    /**
     * Sets instance variables for holding file information and creates a temp directory
     * 
     * @param int $mod_inst_id
     * @param string $temp_path_sub
     */
    public function __construct($mod_inst_id, $temp_path_sub = null){
        global $dataplus, $CFG, $context;

        require_once($CFG->libdir . '/filelib.php');

        $this->fileinfo = array(
            'component' => 'mod_dataplus',
            'filearea' => 'dataplus',
            'contextid' => $context->id, 
            'filepath' => '/'); 

        $this->image_fileinfo = $this->fileinfo;
        $this->image_fileinfo['filearea'] = 'image';

        $this->file_fileinfo = $this->fileinfo;
        $this->file_fileinfo['filearea'] = 'file';

        $this->zip_fileinfo = $this->fileinfo;
        $this->zip_fileinfo['filearea'] = 'zip';

        $dp_temp_path = $CFG->dataroot . '/temp/dataplus/';

        if(!file_exists($dp_temp_path)){
            mkdir($dp_temp_path);
        }

        $this->temp_path = $dp_temp_path . $mod_inst_id;

        if(is_null($temp_path_sub)){
            if(file_exists($this->temp_path)){
               fulldelete($this->temp_path);
            }

            mkdir($this->temp_path);
        } else {
            $this->temp_path .= '/'.$temp_path_sub;

            if(file_exists($this->temp_path)){
                fulldelete($this->temp_path);
            }

            mkdir($this->temp_path);
        }
    }

    
    /**
     * Deletes the temp directory when the helper is no longer needed
     */
    public function close(){
        fulldelete($this->temp_path);
    }


    /**
     * returns the name of the first file found using the parameters
     * 
     * @param int $contextid
     * @param string $filearea
     * @param int $itemid
     * @return mixed
     */
    public function get_file_name($contextid, $filearea, $itemid){
        $fs = get_file_storage();
        $files = $fs->get_area_files($contextid,"mod_dataplus",$filearea,$itemid);

        foreach ($files as $file) {
           $filename = $file->get_filename();

           if (empty($filename) || $filename == '.') {
               continue;
           }

           return $file->get_filename();
        }

        return false;
    }


    /**
     * returns a file path based on fileinfo
     * @param array $fileinfo
     * @return string
     */
    private function get_file_path($fileinfo){
        global $CFG;

        $filename = $this->get_file_name($fileinfo['contextid'],$fileinfo['filearea'],$fileinfo['itemid']);

        return $CFG->wwwroot.'/pluginfile.php/'.$fileinfo['contextid'].'/'.$fileinfo['component'].'/'.
               $fileinfo['filearea'].$fileinfo['filepath'].$fileinfo['itemid'].'/'.$filename;
    }


    /**
     * return the path for the top level
     * 
     * @return string
     */
    public function get_fileinfo(){
        return $this->fileinfo;
    }


    /**
     * return the path for a top level file
     * 
     * @return string
     */
    public function get_top_file_path(){
        return $this->get_file_path($this->get_fileinfo());
    }


    /**
     * return the path for storing images for this module instance
     * 
     * @return string
     */
    public function get_image_fileinfo(){
        return $this->image_fileinfo;
    }


    /**
     * return the path for an image file
     * 
     * @param int $itemid
     * @return string
     */
    public function get_image_file_path($itemid){
        $fileinfo = $this->get_image_fileinfo();
        $fileinfo['itemid'] = $itemid;

        return $this->get_file_path($fileinfo);
    }


    /**
     * return the path for storing files for this module instance
     * 
     * @return string
     */
    public function get_file_fileinfo(){
        return $this->file_fileinfo;
    }


    /**
     * return the path for a file file
     * 
     * @param int $itemid
     * @return string
     */
    public function get_file_file_path($itemid){
        $fileinfo = $this->get_file_fileinfo();
        $fileinfo['itemid'] = $itemid;

        return $this->get_file_path($fileinfo);
    }


    /**
     * return the path for storing zip files for this module instance
     * 
     * @return string
     */
    public function get_zip_fileinfo(){
        return $this->zip_fileinfo;
    }


    /**
     * return the path for a zip file
     * 
     * @param int $itemid
     * @return string
     */
    public function get_zip_file_path($itemid){
        $fileinfo = $this->get_zip_fileinfo();
        $fileinfo['itemid'] = $itemid;

        return $this->get_file_path($fileinfo);
    }

    
    /**
     * check the temp directory for this instance exists, if not create it and return the path
     * 
     * @return string
     */
    public function get_temp_path(){
        return $this->temp_path;
    }

    
    /**
     * return the relative temp path
     * 
     * @return string
     */
    public function get_temp_path_relative(){
        global $CFG;

        return str_replace($CFG->dataroot,'',$this->get_temp_path());
    }

    /**
     * check the tozip directory for this instance exists, if not create it and return the path
     * 
     * @return string
     */
    public function get_tozip_path(){
        global $USER;

        $path = $this->get_temp_path().'/tozip'.$USER->id;

        return $path;
    }


    /**
     * check the tozip image directory for this instance exists, if not create it and return the path
     * 
     * @return string
     */
    public function get_tozip_images_path(){
        $path = $this->get_tozip_path().'/images';

        $this->create_dir($path);

        return $path;
    }


    /**
     * check the tozip image directory for this instance exists, if not create it and return the path
     * 
     * @return string
     */
    public function get_tozip_files_path(){
        $path = $this->get_tozip_path().'/files';

        $this->create_dir($path);

        return $path;
    }


    /**
     * resolves and returns the path for copying to.
     *
     * @param string $type
     * @return string
     */
    public function get_copy_path($type = null){
        if($type == 'image') {
            return $this->get_image_fileinfo();
        } else if ($type == 'file') {
            return $this->get_file_fileinfo();
        } else if ($type == 'temp') {
            return $this->get_temp_path();
        } else if ($type == 'tozip') {
            return $this->get_tozip_path();
        } else if ($type == 'tozip-image') {
            return $this->get_tozip_images_path();
        } else if ($type == 'tozip-file') {
            return $this->get_tozip_files_path();
        }

        return $this->get_path();
    }


    /**
     * Copies the temp dir to the module instance dir, or subdirs for files or images
     *
     * @param string $destination
     * @param array $exclude
     */
    public function copy_temp($destination = null, $exclude = null){
        $path = $this->get_copy_path($destination);
        $temp_path = $this->get_temp_path();

        $this->copy_dir($temp_path,$path,$exclude);
    }


    /**
     * Copy a directory including any subdirs, either from filesystem of Moodle file area.
     *
     * @param mixed $from
     * @param mixed $to
     * @param array $exclude 
     * @return boolean
     */
    public function copy($from,$to,$exclude = array()){
        if(!is_array($from) && !is_array($to)) {
            return $this->copy_filesystem_to_filesystem($from,$to,$exclude);
        } else if(is_array($from) && !is_array($to)) {
            return $this->copy_filearea_to_filesystem($from,$to,$exclude);
        } else if(!is_array($from) && is_array($to)) {
            return $this->copy_filesystem_to_filearea($from,$to,$exclude);
        } else {
            return $this->copy_filearea_to_filearea($from,$to,$exclude);
        }
    }


    /**
     * Copy a directory of file from one location in the file system to another
     *
     * @param string $from = the source directory
     * @param string $to = the destination directory
     * @param array $exclude = array of filenames to exclude from the copy
     * @return boolean
     */
    private function copy_filesystem_to_filesystem($from, $to, $exclude){
        if (is_file($from)) {
            return copy($from,$to);
        }

        $hande = opendir($from);

        while (false !== ($file = readdir($hande))) {
            if ($file == '.' || $file == '..' || in_array($file,$exclude)) {
                continue;
            } else if (is_dir($from.'/'.$file)) {
                $dir = $to.'/'.$file;

                if(file_exists($dir)){
                    fulldelete($dir);
                }

                mkdir($dir);
                $this->copy_filesystem_to_filesystem($from.'/'.$file, $dir, $exclude);
            } else {
                if(!copy($from.'/'.$file, $to.'/'.$file)){
                    return false;
                }
            }
        }

        return true;
    }


    /**
     * Copy a directory from the Moodle file area to the file system
     *
     * @param string $from = the source directory
     * @param string $to = the destination directory
     * @param array $exclude = array of filenames to exclude from the copy
     * @return boolean
     */
    private function copy_filearea_to_filesystem($from, $to, $exclude){
        $fs = get_file_storage();

        if(isset($from['itemid'])){
            $files = $fs->get_area_files($from['contextid'], $from['component'], $from['filearea'], $from['itemid']);
        } else {
            $files = $fs->get_area_files($from['contextid'], $from['component'], $from['filearea']);
        }

        if (empty($files)) {
            return true;
        }

        foreach ($files as $f) {
            if($from['filepath']!= '/' && $from['filepath'] != $f['filepath']){
                continue;
            }

            if (in_array($f->get_filename(),$exclude) || $f->get_filename() == '.' || $f->get_filename() == '..') {
                continue;
            }

            $path = $to.$f->get_filepath();
            
            if (!file_exists($path)) {
                mkdir($path);
            }

            if (!$f->copy_content_to($path.'/'.$f->get_filename())) {
                return false;
            }
        }

        return true;
    }


    /**
     * Copy a directory or file from the file system to the Moodle file area
     *
     * @param string $from = the source directory
     * @param string $to = the destination directory
     * @param array $exclude = array of filenames to exclude from the copy
     * @return boolean
     */       
    private function copy_filesystem_to_filearea($from, $to, $exclude){
        $fs = get_file_storage();

        if (is_file($from)) {
            if (!$fs->create_file_from_pathname($to,$from)) {
                return false;
            }

            return true;
        }

        $hande = opendir($from);

        while (false !== ($file = readdir($hande))) {
            if ($file == '.' || $file == '..' || in_array($file,$exclude)){
                continue;
            }

            $path = $from.'/'.$file;

            if (is_dir($path)) {
                $this->copy_filesystem_to_filearea($path, $to, $exclude);
            } else {
                $filearea_path_raw = str_replace($from,'',$path);
                $filearea_path = substr(0,strrchr($filearea_path_raw,'/')); 
                $filearea_path = $this->resolve_fileinfo_filepath($filearea_path);

                $fileinfo = $to;
                $fileinfo['filepath'] = $filearea_path;
                $fileinfo['filename'] = $file;

                if (!$fs->create_file_from_pathname($fileinfo,$path)) {
                    return false;
                }
            }
        }

        return true;
    }


    /**
     * Resolve file path for use in fileinfo
     * 
     * @param string $filearea_path
     * return string
     */
    public function resolve_fileinfo_filepath($filearea_path){
        if (empty($filearea_path)) {
            $filearea_path = '/';
        }

        if (substr($filearea_path,0,1) != '/') {
            $filearea_path = '/' . $filearea_path;
        }

        if (substr($filearea_path,-1) != '/') {
            $filearea_path .= '/';
        }

        return $filearea_path;
    }


    /**
     * Copy a directory from one location in the Moodle file area to another (note - causes a new file to be created not just another db record)
     *
     * @param string $from = the source directory
     * @param string $to = the destination directory
     * @param array $exclude = array of filenames to exclude from the copy
     * @return boolean
     */
    private function copy_filearea_to_filearea($from, $to, $exclude){
        $fs = get_file_storage();
        $files = $fs->get_area_files($from['contextid'], $from['component'], $from['filearea'], $from['itemid']);

        if (empty($files)) {
            return true;
        }

        foreach ($files as $f) {
            if ($from['filepath'] != '/' && $from['filepath'] != $f['filepath']) {
                continue;
            }

            if (in_array($f->filename,$exclude)) {
                continue;
            }

            $fileinfo = $to;
            $fileinfo['filepath'] = $f->filepath;
            $fileinfo['filename'] = $f->filename;

            $fs->create_file_from_string($fileinfo, $f->get_content());
        }

        return true;
    }


    /**
     * delete all the files referenced in a database column for files or images (used when such a column is deleted or the 
     * type is changed)
     *
     * @param string $col_name
     * @param string $type
     */
    public function delete_column_files($col_name,$type){
        global $dataplus_db;

        $columns = array($col_name);
        $results = $dataplus_db->query_dataplus_database($columns);

        foreach ($results as $result) {
            if (!empty($result->$col_name)) {
                $this->delete_file($result->$col_name,$type);
            }
        }
    }


    /**
     * delete a supporting file or image
     *
     * @param string $name, the filename
     * @param string $type, image or file
     * @return mixed
     */
    public function delete_file($name,$itemid,$type){
        $fs = get_file_storage();

        if (empty($name)) {
            return;
        }

        if ($type == 'image') {
            $fileinfo = $this->get_image_fileinfo();
        } else {
            $fileinfo = $this->get_file_fileinfo();
        }

        $file = $fs->get_file($fileinfo['contextid'], $fileinfo['component'], $fileinfo['filearea'], $itemid,
                    $fileinfo['filepath'], $name);

        if ($file) {
            return $file->delete();
        }

        return true;
    }


    /**
     * create a directory if it doesn't exist.
     *
     * @param string $path
     * @return boolean
     */
    public function create_dir($path){
        if(!file_exists($path)){
            return mkdir($path);
        }

        return false;
    }
}