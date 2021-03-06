<?php

/**
 * Copyright (C) 2014 HB Agency
 * 
 * @author		Blair Winans <bwinans@hbagency.com>
 * @author		Adam Fisher <afisher@hbagency.com>
 * @link		http://www.hbagency.com
 * @license		http://www.gnu.org/licenses/lgpl-3.0.html LGPL
 */

namespace HBAgency\Backend\FormSubmission;

use HBAgency\Model\FormSubmissionModel;
use HBAgency\Model\FormSubmissionDataModel;


class Export extends \Backend
{
	
	/**
	 * Export data
	 *
	 * @access		public
	 * @param		string
	 * @return		void
	 */
	public static function run($dc=null, $strName='formsubmissions', $blnHeaders=true)
	{
		if (!\Input::get('id')) return;
		
		$arrData = array();
		$arrFields = array();
		$arrColumns = array('pid=?');
		$arrValues = array(\Input::get('id'));
		$arrOptions = array();
		
		// HOOK: add custom logic
		if (isset($GLOBALS['TL_HOOKS']['getFormSubmissionForExport']) && is_array($GLOBALS['TL_HOOKS']['getFormSubmissionForExport']))
		{
			foreach ($GLOBALS['TL_HOOKS']['getFormSubmissionForExport'] as $callback)
			{
				list($arrColumns, $arrValues, $arrOptions) = static::importStatic($callback[0])->$callback[1]($arrColumns, $arrValues, $arrOptions, get_called_class());
			}
		}
		
		if (!$arrOptions['order'])
		{
			$arrOptions['order'] = 'id ASC';
		}
		
		// Get submissions
		$objSubmissions = FormSubmissionModel::findBy($arrColumns, $arrValues, $arrOptions);
		
		if ($objSubmissions !== null)
		{
			// Get field names
			$objFields = \Database::getInstance()->prepare("SELECT DISTINCT name FROM ".FormSubmissionDataModel::getTable()." WHERE pid=?")->executeUncached($objSubmissions->first()->id);
			$objSubmissions->reset();
			$arrFields = $objFields->numRows ? $objFields->fetchEach('name') : array();
			
			while ($objSubmissions->next())
			{
				// Get submission data
				$objData = FormSubmissionDataModel::findBy(array('pid=?'), array($objSubmissions->current()->id));
				
				if ($objData !== null)
				{
					// Fill array keys with field names
					$arrRow = array_fill_keys($arrFields, '');
					
					while ($objData->next())
					{
						// Get value even if it's an array
						$varValue = deserialize($objData->current()->value);
						
						if (is_array($varValue))
						{
							$varValue = implode(',', $varValue);
						}
						
						$arrRow[$objData->current()->name] = $varValue;
						$varValue = null;
					}
					
					$arrData[] = $arrRow;
					$arrRow = array();
				}
			}
		}

		// HOOK: add custom logic
		if (isset($GLOBALS['TL_HOOKS']['exportFormSubmissionData']) && is_array($GLOBALS['TL_HOOKS']['exportFormSubmissionData']))
		{
			foreach ($GLOBALS['TL_HOOKS']['exportFormSubmissionData'] as $callback)
			{
				list($arrData, $arrFields, $strName, $blnHeaders) = static::importStatic($callback[0])->$callback[1]($arrData, $arrFields, $strName, $blnHeaders, get_called_class());
			}
		}
		
		// Auto set name
		if ($strName == 'formsubmissions')
		{
			$strName = 'form_' . \Input::get('id') . '_' . $strName;
		}
		
		// Add headers
		if ($blnHeaders && !empty($arrFields))
		{
			array_insert($arrData, 0, array
			(
				$arrFields
			));
		}
		
		static::export($arrData, $strName);
	}
	
}
