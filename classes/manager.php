<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Stash manager.
 *
 * @package    block_stash
 * @copyright  2016 Frédéric Massart - FMCorz.net
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_stash;
defined('MOODLE_INTERNAL') || die();

use context_course;
use context_user;

/**
 * Stash manager.
 *
 * @package    block_stash
 * @copyright  2016 Frédéric Massart - FMCorz.net
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class manager {

    /** @var array Array of singletons. */
    protected static $instances;

    /** @var context The context related to this manager. */
    protected $context;

    /** @var int Course ID. */
    protected $courseid = null;

    /** @var stash The stash object, do not refer to directly as it's lazy loaded. */
    protected $stash;

    /**
     * Constructor.
     *
     * @param int $courseid The course ID.
     * @return void
     */
    protected function __construct($courseid) {
        $courseid = intval($courseid);
        $this->context = context_course::instance($courseid);
        $this->courseid = $courseid;
    }

    /**
     * Create or update an item based on the data passed.
     *
     * @param stdClass $data Data to use to create or update.
     * @param int $draftitemid Draft item ID of the current user to get the image from.
     * @return item
     */
    public function create_or_update_item($data, $draftitemid) {
        globaL $USER;

        // TODO Capability checks.
        $item = new item(null, $data);
        if (!$item->get_id()) {
            $item->create();
        } else {
            $item->update();
        }

        // Rename the image to 'image.ext', in case we want to add a second one later.
        $fs = get_file_storage();
        $usercontextid = context_user::instance($USER->id)->id;
        $files = $fs->get_area_files($usercontextid, 'user', 'draft', $draftitemid, '', false);
        $image = array_pop($files);
        if ($image) {

            $ext = strtolower(pathinfo($image->get_filename(), PATHINFO_EXTENSION));
            $filename = 'image' . ($ext ? '.' . $ext : '');
            // Check that we don't already have this image saved before renaming it.
            if (!$fs->file_exists($usercontextid, 'user', 'draft', $draftitemid, '/', $filename)) {
                $image->rename('/', $filename);
            }
        }

        $fileareaoptions = ['maxfiles' => 1];
        file_save_draft_area_files($draftitemid, $this->context->id, 'block_stash', 'item', $item->get_id(), $fileareaoptions);

        return $item;
    }

    /**
     * Get an instance of the manager.
     *
     * @param int $courseid The course ID.
     * @param bool $forcereload Force the reload of the singleton, to invalidate local cache.
     * @return manager The instance of the manager.
     */
    public static function get($courseid, $forcereload = false) {
        global $CFG;

        $courseid = intval($courseid);
        if ($forcereload || !isset(self::$instances[$courseid])) {
            self::$instances[$courseid] = new static($courseid);
        }
        return self::$instances[$courseid];
    }

    /**
     * Get the manager by item ID.
     *
     * @param int $itemid The item ID.
     * @return manager
     */
    public static function get_by_itemid($itemid) {
        $stash = stash::get_by_itemid($itemid);
        $manager = self::get($stash->get_courseid());
        $manager->stash = $stash;
        return $manager;
    }

    /**
     * Get the course ID.
     *
     * @return int
     */
    public function get_courseid() {
        return $this->courseid;
    }

    /**
     * Get the context.
     *
     * @return context
     */
    public function get_context() {
        return $this->context;
    }

    /**
     * Get the stash.
     *
     * @return stash
     */
    public function get_stash() {
        if (!$this->stash) {
            $stash = stash::get_record(['courseid' => $this->courseid]);
            if (!$stash) {
                $stash = new stash(null, (object) ['courseid' => $this->courseid]);
                $stash->create();
            }
            $this->stash = $stash;
        }
        return $this->stash;
    }

    /**
     * Get an item.
     *
     * @param int $itemid The item ID.
     * @return item
     */
    public function get_item($itemid) {
        return new item($itemid);
        if (!$item->get_stashid() !== $this->get_stash()->get_id()) {
            throw new coding_exception('Unexpected item ID.');
        }
    }

    /**
     * Get the items defined in this course.
     *
     * @return item[]
     */
    public function get_items() {
        return item::get_records(['stashid' => $this->get_stash()->get_id()]);
    }

    /**
     * Get the item of a user.
     *
     * @param int $userid The user ID.
     * @param int $itemid The item ID.
     * @return user_item
     */
    public function get_user_item($userid, $itemid) {
        $params = ['userid' => $userid, 'itemid' => $itemid];

        $ui = user_item::get_record($params);
        if (!$ui) {
            $ui = new user_item(null, (object) $params);
            $ui->create();
        }

        return $ui;
    }

    /**
     * Is the stash enabled in the course?
     *
     * @return boolean True if enabled.
     */
    public function is_enabled() {
        // TODO Add logic.
        return true;
    }

    /**
     * Throws an exception when the user cannot manage the stash.
     *
     * @return void
     */
    public function require_manage() {
        // TODO Implement logic.
    }

}