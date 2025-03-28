<?php

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao;

/**
 * Class FormCheckbox
 *
 * @property array $options
 */
class FormCheckbox extends Widget
{
	/**
	 * Submit user input
	 *
	 * @var boolean
	 */
	protected $blnSubmitInput = true;

	/**
	 * Template
	 *
	 * @var string
	 */
	protected $strTemplate = 'form_checkbox';

	/**
	 * Error message
	 *
	 * @var string
	 */
	protected $strError = '';

	/**
	 * The CSS class prefix
	 *
	 * @var string
	 */
	protected $strPrefix = 'widget widget-checkbox';

	/**
	 * Add specific attributes
	 *
	 * @param string $strKey   The attribute name
	 * @param mixed  $varValue The attribute value
	 */
	public function __set($strKey, $varValue)
	{
		switch ($strKey)
		{
			case 'options':
				$this->arrOptions = StringUtil::deserialize($varValue);
				break;

			case 'rgxp':
			case 'minlength':
			case 'maxlength':
			case 'minval':
			case 'maxval':
				// Ignore
				break;

			default:
				parent::__set($strKey, $varValue);
				break;
		}
	}

	/**
	 * Return a parameter
	 *
	 * @param string $strKey The parameter key
	 *
	 * @return mixed The parameter value
	 */
	public function __get($strKey)
	{
		if ($strKey == 'options')
		{
			return $this->arrOptions;
		}

		return parent::__get($strKey);
	}

	/**
	 * Check the options if the field is mandatory
	 */
	public function validate()
	{
		$mandatory = $this->mandatory;
		$options = $this->getPost($this->strName);

		// Check if there is at least one value
		if ($mandatory && \is_array($options))
		{
			foreach ($options as $option)
			{
				if (\strlen($option))
				{
					$this->mandatory = false;
					break;
				}
			}
		}

		$varInput = $this->validator($options);

		// Check for a valid option (see #4383)
		if (!empty($varInput) && !$this->isValidOption($varInput))
		{
			$this->addError($GLOBALS['TL_LANG']['ERR']['invalid']);
		}

		// Add class "error"
		if ($this->hasErrors())
		{
			$this->class = 'error';
		}
		else
		{
			$this->varValue = $varInput;
		}

		// Reset the property
		if ($mandatory)
		{
			$this->mandatory = true;
		}
	}

	/**
	 * Return all attributes as string
	 *
	 * @param array $arrStrip An optional array with attributes to strip
	 *
	 * @return string The attributes string
	 */
	public function getAttributes($arrStrip=array())
	{
		// The "required" attribute only makes sense for single checkboxes
		if ($this->mandatory && \count($this->arrOptions) == 1)
		{
			$this->arrAttributes['required'] = 'required';
		}

		return parent::getAttributes($arrStrip);
	}

	/**
	 * Generate the options
	 *
	 * @return array The options array
	 */
	protected function getOptions()
	{
		$arrOptions = array();
		$blnHasGroups = false;

		foreach ($this->arrOptions as $i=>$arrOption)
		{
			if ($arrOption['group'] ?? null)
			{
				if ($blnHasGroups)
				{
					$arrOptions[] = array
					(
						'type' => 'group_end'
					);
				}

				$arrOptions[] = array
				(
					'type'  => 'group_start',
					'label' => StringUtil::specialchars($arrOption['label'] ?? '')
				);

				$blnHasGroups = true;
			}
			else
			{
				$arrOptions[] = array_replace
				(
					$arrOption,
					array
					(
						'type'       => 'option',
						'name'       => $this->strName . ((\count($this->arrOptions) > 1) ? '[]' : ''),
						'id'         => $this->strId . '_' . $i,
						'value'      => $arrOption['value'] ?? null,
						'checked'    => $this->isChecked($arrOption),
						'attributes' => $this->getAttributes(),
						'label'      => $arrOption['label'] ?? null
					)
				);
			}
		}

		if ($blnHasGroups)
		{
			$arrOptions[] = array
			(
				'type' => 'group_end'
			);
		}

		return $arrOptions;
	}

	/**
	 * Override the parent method and inject the error message inside the fieldset (see #3392)
	 *
	 * @param boolean $blnSwitchOrder If true, the error message will be shown below the field
	 *
	 * @return string The form field markup
	 */
	public function generateWithError($blnSwitchOrder=false)
	{
		$this->strError = $this->getErrorAsHTML();

		return $this->generate();
	}

	/**
	 * Generate the widget and return it as string
	 *
	 * @return string The widget markup
	 */
	public function generate()
	{
		$strOptions = '';

		foreach ($this->arrOptions as $i=>$arrOption)
		{
			$strOptions .= sprintf(
				'<span><input type="checkbox" name="%s" id="opt_%s" class="checkbox" value="%s"%s%s%s <label id="lbl_%s" for="opt_%s">%s</label></span> ',
				$this->strName . ((\count($this->arrOptions) > 1) ? '[]' : ''),
				$this->strId . '_' . $i,
				$arrOption['value'] ?? null,
				$this->isChecked($arrOption),
				$this->getAttributes(),
				$this->strTagEnding,
				$this->strId . '_' . $i,
				$this->strId . '_' . $i,
				$arrOption['label'] ?? null
			);
		}

		if ($this->strLabel)
		{
			return sprintf(
				'<fieldset id="ctrl_%s" class="checkbox_container%s"><legend>%s%s%s</legend>%s<input type="hidden" name="%s" value=""%s%s</fieldset>',
				$this->strId,
				($this->strClass ? ' ' . $this->strClass : ''),
				($this->mandatory ? '<span class="invisible">' . $GLOBALS['TL_LANG']['MSC']['mandatory'] . ' </span>' : ''),
				$this->strLabel,
				($this->mandatory ? '<span class="mandatory">*</span>' : ''),
				$this->strError,
				$this->strName,
				$this->strTagEnding,
				$strOptions
			);
		}

		return sprintf(
			'<fieldset id="ctrl_%s" class="checkbox_container%s">%s<input type="hidden" name="%s" value=""%s%s</fieldset>',
			$this->strId,
			($this->strClass ? ' ' . $this->strClass : ''),
			$this->strError,
			$this->strName,
			$this->strTagEnding,
			$strOptions
		);
	}
}
