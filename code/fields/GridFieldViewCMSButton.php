<?php

/**
 *
 *
 * @author <marcus@silverstripe.com.au>
 * @license BSD License http://www.silverstripe.org/bsd-license
 */
class GridFieldViewCMSButton implements GridField_ColumnProvider {
	public function augmentColumns($field, &$cols) {
		if(!in_array('Actions', $cols)) $cols[] = 'Actions';
	}

	public function getColumnsHandled($field) {
		return array('Actions');
	}

	public function getColumnContent($field, $record, $col) {
		if($record->canView()) {
			$link = '';
			$item = $record->getRealItem();

			if ($item instanceof CMSPreviewable) {
				$link = $record->CMSEditLink();
			} else if ($item instanceof Folder) {
				$link = Controller::join_links('admin/assets/show/', $item->ID);
			} else if ($item instanceof File) {
				$link = Controller::join_links('admin/assets/EditForm/field/File/item', $item->ID, 'edit');
			}

			if ($link) {
				$data = new ArrayData(array(
					'Link' => $link
				));
				return $data->renderWith('GridFieldViewButton');
			} else {
				return '';
			}
		}
	}

	public function getColumnAttributes($field, $record, $col) {
		return array('class' => 'col-buttons');
	}

	public function getColumnMetadata($gridField, $col) {
		return array('title' => null);
	}
}
