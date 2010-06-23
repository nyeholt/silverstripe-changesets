<?php
/* All code covered by the BSD license located at http://silverstripe.org/bsd-license/ */

/**
 * Object that represents an item in a content changeset
 *
 * @author Marcus Nyeholt <marcus@silverstripe.com.au>
 */
class ContentChangesetItem extends DataObject {
    public static $db = array(
		'OtherID' => 'Int',
		'OtherClass' => 'Varchar(32)',
	);

	public static $has_one = array(
		'Changeset' => 'ContentChangeset',
	);

	public function getRealItem() {
		return DataObject::get_by_id($this->OtherClass, $this->OtherID);
	}
}