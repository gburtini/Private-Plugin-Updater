<?php
/*
 * Copyright © 2011 Giuseppe Burtini <joe@truephp.com>. Most rights reserved.
 *
 *  The Private Plugin Updater is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    The Private Plugin Updater is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with the Private Plugin Updater.  If not, see <http://www.gnu.org/licenses/>.
 *
 * Please contact me if you would like to discuss alternative licensing scenarios.
 *
 * Very simple update class. Implements updates for non-WordPress codex plugins, in
 * the form of a remote hosted JSON file (just like the codex uses).
 *
 * Example usage:

   $plugin = new PPU_Plugin();
   $plugin->file = "madeupplugin.php";
   $plugin->slug = "madeupplugin";
   $plugin->version = 1;
   $ppu = new PPU_Updater($plugin, "http://www.yoursite.com/update.json");

 * OR, just call:

   ppu_easy_updater("madeupplugin.php", 1, "http://www.yoursite.com/update.json");

 * You can set this up as early as outside of any actions, but you should definitely
 * do it in init at the latest.
 *
 * The JSON file at update.json must contain name, slug (must match slug above), package (the URL to download the ZIP)
 * and version (to be compared according to version_compare).
 *
 * Note, that as this example is written right now, it makes the call to yoursite.com on
 * every single page load. You can now implement a cache by passing in a KVS object (see my WPKVS class) or a
 * string for the option name you wish to store data in and it will be stored for PPU_CACHE_PERIOD
 */

if(!defined("PPU_CACHE_PERIOD"))
   define("PPU_CACHE_PERIOD", 60*60*24);

if(!function_exists("ppu_easy_updater"))
{
   function ppu_easy_updater($plugin_file, $plugin_current_version, $update_url, $plugin_slug=null, $kvs_or_optionname=null) {
      $plugin = new PPU_Plugin();
      $plugin->file = $plugin_file;
      if($plugin_slug !== null)
         $plugin->slug = $plugin_slug;
      else
         $plugin->slug = basename($plugin_file);

      $plugin->version = $plugin_current_version;
      $ppu = new PPU_Updater($plugin, $update_url, $kvs_or_optionname);
      return $ppu;
   }
}

if(!class_exists("PPU_Updater")) {
   class PPU_Updater {
      private $plugin;
      private $update;
      private $checkURL;
      private $kvs;

      function __construct($plugin, $checkURL, $kvs_or_optionname=null) {
         $this->plugin = $plugin;
         $this->checkURL = $checkURL;
         $this->kvs = $kvs_or_optionname;
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

         if(!is_object($update))
            return $value;

         if(version_compare($this->plugin->version, $update->version, "<"))
            $value->response[$this->plugin->file] = (object) $update;

         return $value;
      }

      public function checkUpdate($force=false) {
         if(isset($this->update) && !$force)
            return $this->update;

         if(false !== ($cache = $this->loadCache()))
            return $cache;

         add_action("ppu_checking_update", $this->plugin);

         $url = add_query_arg(
            // this filter allows you to add licensing functionality (send the license along with the json request).
            apply_filters("ppu_update_arguments", array("version" => $this->plugin->version))
         , $this->checkURL);

         $result = wp_remote_get($url);
         if(!is_wp_error($result))
         {
            $this->update = new PPU_Plugin($result['body']);
            $this->storeCache($this->update);
            return $this->update;
         }

         return false;
      }

      private function storeCache($update) {
         $store = (object) array('time'=>time(), 'update'=>$update);
         if(is_object($this->kvs))
            $this->kvs->set("update_cache", $store);
         else if(is_string($this->kvs))
            update_option($this->kvs, $store);
      }

      private function loadCache() {
         $cache_period = PPU_CACHE_PERIOD;

         if(is_object($this->kvs))
            $update_cache = $this->kvs->get("update_cache");
         else if(is_string($this->kvs))
            $update_cache = get_option($this->kvs);
         else
            return false;

         if(!is_object($update_cache) || time() - $update_cache->time > $cache_period)
            return false;

         return $update_cache->update;
      }

   }

   // just a storage container for all the relevant variables.
   class PPU_Plugin {
      public $name;
      public $slug;

      // for the update script
      public $package;

      // for the info script.
      public $new_version;
      public $version;
      public $requires;
      public $tested;
      public $author;

      public $rating;
      public $upgrade_notice;
      public $num_ratings;
      public $downloaded;
      public $homepage;
      public $last_updated;

      public $download_url;
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
}