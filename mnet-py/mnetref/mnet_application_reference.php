<?php

/**
 * MNet Library 
 * Copyright (C) 2006-2008 Catalyst IT Ltd (http://www.catalyst.net.nz)
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @package    mnet
 * @subpackage reference
 * @author     Catalyst IT Ltd
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2006-2008 Catalyst IT Ltd http://catalyst.net.nz
 * @copyright  (C) portions from Moodle, (C) Martin Dougiamas http://dougiamas.com
 *
 */

require MNET_DIR.'/mnet_application.php';

/**
 * This class is a reference implementation of mnet_application,
 * the abstraction layer for integrating MNet with a host system.
 * 
 * This reference implementation stores files in a temporary directory,
 * and creates a SQLite database for storing persistent data. You will
 * need to have the PDO and SQLite PHP extensions enabled to use it.
 *
 * To use it, you should set the document root of your web server to
 * this directory, and call the various scripts to communicate with a remote
 * peer.
 * 
 * You may need to manually alter the SQLite database to add new peers.
 * 
 * Feel free to copy this class and adapt it to your local application.
 *
 */
class mnet_reference_system extends mnet_application {
    private $local_wwwroot;
    private $dir_base;

    /**
     * Initialise this reference implementation
     * 
     * This will create a SQLite database under $dir_base to store
     * config and peers under
     * 
     * @param string $local_wwwroot the local wwwroot of this reference implementation (eg. http://test1.example.com)
     * @param string $dir_base the directory base to use
     */
    public function __construct($local_wwwroot, $dir_base) {
        $this->local_wwwroot = $local_wwwroot;
        $this->dir_base = $dir_base;

        if (!file_exists($dir_base)) {
            mkdir($dir_base, 0777, true);
        }

        if (!file_exists($dir_base . '/peers/')) {
            mkdir($dir_base.'/peers/', 0777, true);
        }

        if (!file_exists($dir_base . '/key_history')) {
            file_put_contents($dir_base . '/key_history', array());
        }

    }
    
    /**
     * Return our discovered wwwroot
     *
     * @return string the discovered wwwroot of this reference instance
     */
    public function get_local_wwwroot() {
        return $this->local_wwwroot;
    }

    /**
     * Logs a message to stderr
     */
    public function log($type, $message) {
        file_put_contents($this->dir_base.'/log', date('c').': '.$type.': '.$message.PHP_EOL, FILE_APPEND);
    }
    
    public function get_current_keypair() {
        $history = unserialize(file_get_contents($this->dir_base.'/key_history'));
        if (count($history)) {
            return $history[count($history) - 1];
        }
        else {
            return null;
        }
    }

    public function get_keypair_history() {
        return unserialize(file_get_contents($this->dir_base.'/key_history'));
    }

    public function set_new_keypair($certificate, $keypair_pem) {
        $history = unserialize(file_get_contents($this->dir_base.'/key_history'));
        $history[] = array('certificate' => $certificate, 'keypair_PEM' => $keypair_pem);
        file_put_contents($this->dir_base.'/key_history', serialize($history));
    }

    public function get_peer_by_wwwroot($wwwroot) {
        $filename = $this->dir_base.'/peers/'.md5($wwwroot);
        if (!file_exists($filename)) {
            return null;
        }
        $peer = unserialize(file_get_contents($filename));
        return mnet_peer::populate($peer['wwwroot'], $peer['cert'], $peer['serverpath'], $peer['protocol']);
    }

    public function get_public_key_url() {
        return $this->local_wwwroot.'/publickey.php';
    }

    public function save_peer(mnet_peer &$peer) {
        $arr = array('wwwroot' => $peer->get_wwwroot(), 'cert' => $peer->get_public_key(), 'serverpath' => $peer->get_server_path(), 'protocol' => $peer->get_protocol());
        file_put_contents($this->dir_base.'/peers/'.md5($peer->get_wwwroot()), serialize($arr));
    }

    public function peer_is_trusted(mnet_peer $peer) {
        return false;
    }
    
    public function get_server_path() {
        return '/server.php';
    }
    
    /**
      * Returns a list of peers known to this reference implementation
      * 
      * Note this is not a standard mnet_application API method; it is
      * provided for convenience.
      *
      * @return array an array of mnet_peer records
      */
    public function get_all_peers() {
        return array();
    }
}
