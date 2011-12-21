<?php

define("PPU_UPDATE_FREQUENCY", 1);
class PPU_Updater {
   private $plugin;
   private $checkURL;
   private $kvs;

   function __construct($plugin, $checkURL, $kvs) {
      $this->plugin = $plugin;
      $this->checkURL = $checkURL;
      $this->kvs = $kvs;

      // set up hooks
      add_filter("plugins_api", array(&$this, "plugins_api"));
      add_filter("site_transient_update_plugins", array(&$this, "site_transient_update_plugins"));
   }

   /*
    * returns the relevant update data to the WordPress.org update check
    */
   public function plugins_api($deprecated, $action, $args) {
      if($action != "plugin_information")
         return false;

      if($args->slug != $this->plugin->slug)
         return false;

      $update = $this->checkUpdate();
      if(!$update);
         return false;

      return (object) $update;
   }

   /*
    * adds the update nag to the plugins screen
    */
   public function site_transient_update_plugins($value) {
      $update = $this->kvs->get("update");
      if(version_compare($this->plugin->version, $update->version, "<"))
         $value->response[$this->plugin->file] = (object) $update;

      return $value;
   }

   public function checkUpdate() {
      $url = add_query_arg("version", $this->plugin->version, $url);
		$result = wp_remote_get($url);
      if(!is_wp_error($result))
      {
         $update = new PPU_Plugin($result['body']);
         $this->kvs->set("update", $update);
         return $update;
      }

      return false;
   }

}

class PPU_Plugin {
   public $name;
   public $slug;

   public $version;
   public $requires;
   public $tested;
   public $rating;
   public $upgrade_notice;
   public $num_ratings;
   public $downloaded;
   public $homepage;
   public $last_updated;

   public $url;
   public $download_link;
   public $author;
   public $sections = array();

   function __construct($json=null) {
      if($json !== null)
         $this->loadJSON($json);
   }

   public function loadJSON($json) {
      $response = get_object_vars(json_decode($json));
      foreach(($response) as $name=>$value)
      {
         $this->$name = $value;
      }
   }
}
