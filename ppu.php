<?php
/*
 * Very simple update class. Implements updates for non-WordPress codex plugins, in
 * the form of a remote hosted JSON file (just like the codex uses).
 *
 * Example usage:

   $plugin = new PPU_Plugin();
   $plugin->file = "madeupplugin.php";
   $plugin->slug = "madeupplugin";
   $plugin->version = 1;
   $ppu = new PPU_Updater($plugin, "http://www.yoursite.com/update.json");

 *
 * Note, that as this is written right now, it makes the call to yoursite.com on
 * every single page load. You will want to change this to be caching before deploying it.
 */
class PPU_Updater {
   private $plugin;
   private $update;
   private $checkURL;

   function __construct($plugin, $checkURL) {
      $this->plugin = $plugin;
      $this->checkURL = $checkURL;

      // set up hooks
      add_filter("plugins_api", array(&$this, "plugins_api"), 10, 3);
      add_filter("site_transient_update_plugins", array(&$this, "site_transient_update_plugins"));
   }

   /*
    * returns the relevant update data to the WordPress.org update check
    */
   public function plugins_api($deprecated, $action=null, $args=null) {
      if($action != "plugin_information")
        return false;

      if($args->slug != $this->plugin->slug)
         return false;

      add_action("ppu_adding_plugin_info", $this->plugin);

      $obj = $this->checkUpdate();
      return (object) $obj;
   }

   /*
    * adds the update nag to the plugins screen
    */
   public function site_transient_update_plugins($value) {
      add_action("ppu_adding_nag", $this->plugin);

      $update = $this->checkUpdate();

      if($update === null)
         return $value;

      if(version_compare($this->plugin->version, $update->version, "<"))
         $value->response[$this->plugin->file] = (object) $update;

      return $value;
   }

   public function checkUpdate($force=false) {
      if(isset($this->update) && !$force)
         return $this->update;

      add_action("ppu_checking_update", $this->plugin);

      $url = add_query_arg(
         // this filter allows you to add licensing functionality (send the license along with the json request).
         apply_filters("ppu_update_arguments", array("version" => $this->plugin->version))
      , $this->checkURL);

		$result = wp_remote_get($url);
      if(!is_wp_error($result))
      {
         $this->update = new PPU_Plugin($result['body']);
         return $this->update;
      }

      return false;
   }

}

// just a storage container for all the relevant variables.
class PPU_Plugin {
   public $name;
   public $slug;

   public $new_version;
   public $package;

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
   private function castSectionsToArray()
   {
      $this->sections = get_object_vars($this->sections);
   }
   public function loadJSON($json) {
      $response = get_object_vars(json_decode($json));
      foreach(($response) as $name=>$value)
      {
         $this->$name = $value;
      }
      $this->castSectionsToArray();
   }
}
