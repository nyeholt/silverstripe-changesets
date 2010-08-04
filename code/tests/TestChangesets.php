<?php
/*

Copyright (c) 2009, SilverStripe Australia PTY LTD - www.silverstripe.com.au
All rights reserved.

Redistribution and use in source and binary forms, with or without modification, are permitted provided that the following conditions are met:

    * Redistributions of source code must retain the above copyright notice, this list of conditions and the following disclaimer.
    * Redistributions in binary form must reproduce the above copyright notice, this list of conditions and the following disclaimer in the
      documentation and/or other materials provided with the distribution.
    * Neither the name of SilverStripe nor the names of its contributors may be used to endorse or promote products derived from this software
      without specific prior written permission.

THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE
LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE
GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT,
STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY
OF SUCH DAMAGE.
*/

/**
 * @author Marcus Nyeholt <marcus@silverstripe.com.au>
 */
class TestChangesets extends SapphireTest
{
	public static $fixture_file = 'changesets/code/tests/Changesets.yml';

	public function setupOnce() {
		parent::setupOnce();
		$this->resetDBSchema();

		// apply the ChangesetTrackable extension
		DataObject::add_extension('SiteTree', 'ChangesetTrackable');
	}

    public function setUp() {
		parent::setUp();
		$this->cleanObjects();
	}

	protected function cleanObjects() {
		$objs = DataObject::get('ContentChangeset');
		if ($objs) {
			foreach ($objs as $obj) {
				$obj->delete();
			}
		}
		$objs = DataObject::get('ContentChangesetItem');
		if ($objs) {
			foreach ($objs as $obj) {
				$obj->delete();
			}
		}
	}


	public function testCreateChangset() {

		$this->logInWithPermission('CMS_ACCESS_CMSMain');
		
		// create some content and make a change. When saving, it should be added to the current user's
		// active changeset
		$obj = $this->objFromFixture('Page', 'home');

		$obj->Title = "changing home";
		$obj->write();

		// it should have a changeset now
		$cs1 = $obj->getCurrentChangeset();
		$this->assertNotNull($cs1);
		$this->assertEquals("Active", $cs1->Status);
	}

	public function testCreateNewContent() {
		$this->logInWithPermission('CMS_ACCESS_CMSMain');
		$mid = Member::currentUserID();
		// create some content and make a change. When saving, it should be added to the current user's
		// active changeset
		$obj = new Page();
		$obj->Title = "New Page";
		$obj->write();

		// it should have a changeset now
		$cs1 = $obj->getCurrentChangeset();
		$this->assertNotNull($cs1);

		$obj->Title = "New Page Title";
		$obj->write();
		$cs1 = $obj->getCurrentChangeset();

		$this->assertNotNull($cs1);
		$this->assertEquals(1, $cs1->getItems()->Count());
		$this->assertEquals("Active", $cs1->Status);
	}

	public function testUpdateChangset() {
		$this->logInWithPermission('CMS_ACCESS_CMSMain');
		// create some content and make a change. When saving, it should be added to the current user's
		// active changeset
		$obj = $this->objFromFixture('Page', 'home');

		$obj->Title = "changing home";
		$obj->write();

		// it should have a changeset now
		$cs1 = $obj->getCurrentChangeset();
		$this->assertNotNull($cs1);

		$obj2 = $this->objFromFixture('Page', 'about');
		$obj2->Content = "blah";
		$obj2->write();

		$cs3 = $obj->getCurrentChangeset();
		$this->assertNotNull($cs3);

		$this->assertEquals($cs3->ID, $cs1->ID);
		$this->assertEquals(2, $cs3->getItems()->Count());
	}

	public function testGetUserChangset() {
		$this->logInWithPermission('CMS_ACCESS_CMSMain');

		// create some content and make a change. When saving, it should be added to the current user's
		// active changeset
		$obj = $this->objFromFixture('Page', 'home');
		$obj->Title = "changing home";
		$obj->write();

		// it should have a changeset now
		$cs1 = $obj->getCurrentChangeset();
		$this->assertNotNull($cs1);

		$cs2 = singleton('ChangesetService')->getChangesetForUser();
		$this->assertNotNull($cs2);

		$this->assertEquals($cs1->ID, $cs2->ID);
	}

	public function testRevertItemInChangeset() {
		$this->logInWithPermission('CMS_ACCESS_CMSMain');

		$obj = $this->objFromFixture('Page', 'home');
		$obj->Title = "Not the homepage";
		$obj->write();

		$this->logInWithPermission('ADMIN');
		$obj->doPublish();
		$this->assertEquals('Published', $obj->Status);
		
		$this->logInWithPermission('CMS_ACCESS_CMSMain');
		
		$obj->Title = "Not the homepage 2";
		$obj->write();

		// @TODO - this status change should be automatic on changing the object
		// $this->assertEquals('Saved (Updated)', $obj->Status);

		$obj2 = $this->objFromFixture('Page', 'about');
		$obj2->Title = "blah";
		$obj2->write();

		$cs = singleton('ChangesetService')->getChangesetForUser();
		$this->assertNotNull($cs);
		$this->assertEquals(2, $cs->getItems()->Count());

		// now make sure that the change we have is the correct one
		$modded = $cs->getItems()->First();
		$this->assertEquals($obj->Title, $modded->Title);

		$cs->revert($modded);

		// should now only have 1 item
		$this->assertEquals(1, $cs->getItems()->Count());

		// that should have rolled it back to the previous version also, so reload and ensure it is
		// the previous version
		$obj = DataObject::get_by_id('Page', $obj->ID);
		$this->assertEquals('Published', $obj->Status);
		$this->assertEquals("Not the homepage", $obj->Title);

		$modded = $cs->getItems()->First();
		$this->assertEquals($obj2->Title, $modded->Title);

		$obj3 = $this->objFromFixture('Page', 'another');
		$obj3->Title = "fadfsf";
		$obj3->write();

		$this->assertEquals(2, $cs->getItems()->Count());
		
		$cs->revertAll();
		$this->assertEquals(0, $cs->getItems()->Count());
	}

	public function testCannotPublish() {
		$this->logInWithPermission('CMS_ACCESS_CMSMain');

		$obj = $this->objFromFixture('Page', 'home');
		$obj->Title = "Not the homepage";
		$obj->write();
		
		$obj->doPublish();

		// should not be able to publish
		$this->assertEquals('New page', $obj->Status);
	}

	public function testSubmitChangeset() {
		$this->logInWithPermission('CMS_ACCESS_CMSMain');

		$obj = $this->objFromFixture('Page', 'home');
		$obj->Title = "Not the homepage";
		$obj->write();

		$obj->doPublish();

		// should not be able to publish
		$this->assertEquals('New page', $obj->Status);

		$obj2 = $this->objFromFixture('Page', 'about');
		$obj2->Title = "blah";
		$obj2->write();

		$cs = singleton('ChangesetService')->getChangesetForUser();
		$this->assertNotNull($cs);
		$this->assertEquals(2, $cs->getItems()->Count());

		$cs->submit();

		$this->assertEquals('Published', $cs->Status);

		foreach ($cs->getItems() as $item) {
			$this->assertEquals('Published', $item->Status);
		}

		// now make sure that a new changeset is created when i go to edit again
		$obj->Title = "Not the homepage really";
		$obj->write();
		$obj2->Title = "This is actually";
		$obj2->write();

		$cs2 = singleton('ChangesetService')->getChangesetForUser();
		$cs3 = singleton('ChangesetService')->getChangesetForContent($obj, 'Active');

		$this->assertEquals($cs2->ID, $cs3->ID);
		$this->assertNotEquals($cs->ID, $cs2->ID);

		$this->assertEquals(2, $cs2->getItems()->Count());
	}
}