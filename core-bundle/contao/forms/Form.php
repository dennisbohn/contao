<?php

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao;

use Contao\CoreBundle\Routing\ResponseContext\HtmlHeadBag\HtmlHeadBag;
use Contao\CoreBundle\Session\Attribute\AutoExpiringAttribute;

/**
 * Provide methods to handle front end forms.
 *
 * @property integer $id
 * @property string  $title
 * @property string  $formID
 * @property string  $method
 * @property boolean $allowTags
 * @property string  $attributes
 * @property boolean $novalidate
 * @property integer $jumpTo
 * @property boolean $sendViaEmail
 * @property boolean $skipEmpty
 * @property string  $format
 * @property string  $recipient
 * @property string  $subject
 * @property boolean $storeValues
 * @property string  $targetTable
 * @property string  $customTpl
 */
class Form extends Hybrid
{
	public const SESSION_KEY = 'contao.form.data';

	/**
	 * Model
	 * @var FormModel
	 */
	protected $objModel;

	/**
	 * Key
	 * @var string
	 */
	protected $strKey = 'form';

	/**
	 * Table
	 * @var string
	 */
	protected $strTable = 'tl_form';

	/**
	 * Template
	 * @var string
	 */
	protected $strTemplate = 'form_wrapper';

	/**
	 * Remove name attributes in the back end so the form is not validated
	 *
	 * @return string
	 */
	public function generate()
	{
		$request = System::getContainer()->get('request_stack')->getCurrentRequest();

		if ($request && System::getContainer()->get('contao.routing.scope_matcher')->isBackendRequest($request))
		{
			$objTemplate = new BackendTemplate('be_wildcard');
			$objTemplate->wildcard = '### ' . $GLOBALS['TL_LANG']['CTE']['form'][0] . ' ###';
			$objTemplate->id = $this->id;
			$objTemplate->link = $this->title;
			$objTemplate->href = StringUtil::specialcharsUrl(System::getContainer()->get('router')->generate('contao_backend', array('do'=>'form', 'table'=>'tl_form_field', 'id'=>$this->id)));

			return $objTemplate->parse();
		}

		if ($this->customTpl)
		{
			$request = System::getContainer()->get('request_stack')->getCurrentRequest();

			// Use the custom template unless it is a back end request
			if (!$request || !System::getContainer()->get('contao.routing.scope_matcher')->isBackendRequest($request))
			{
				$this->strTemplate = $this->customTpl;
			}
		}

		return parent::generate();
	}

	/**
	 * Generate the form
	 */
	protected function compile()
	{
		$hasUpload = false;
		$doNotSubmit = false;
		$arrSubmitted = array();

		$this->loadDataContainer('tl_form_field');
		$formId = $this->formID ? 'auto_' . $this->formID : 'auto_form_' . $this->id;

		$this->Template->fields = '';
		$this->Template->hidden = '';
		$this->Template->formSubmit = $formId;
		$this->Template->method = ($this->method == 'GET') ? 'get' : 'post';

		$arrLabels = array();
		$arrFiles = array();

		// Get all form fields
		$arrFields = array();
		$objFields = FormFieldModel::findPublishedByPid($this->id);

		if ($objFields !== null)
		{
			while ($objFields->next())
			{
				// Ignore the name of form fields which do not use a name (see #1268)
				if ($objFields->name && isset($GLOBALS['TL_DCA']['tl_form_field']['palettes'][$objFields->type]) && preg_match('/[,;]name[,;]/', $GLOBALS['TL_DCA']['tl_form_field']['palettes'][$objFields->type]))
				{
					$arrFields[$objFields->name] = $objFields->current();
				}
				else
				{
					$arrFields[] = $objFields->current();
				}
			}
		}

		// HOOK: compile form fields
		if (isset($GLOBALS['TL_HOOKS']['compileFormFields']) && \is_array($GLOBALS['TL_HOOKS']['compileFormFields']))
		{
			foreach ($GLOBALS['TL_HOOKS']['compileFormFields'] as $callback)
			{
				$this->import($callback[0]);
				$arrFields = $this->{$callback[0]}->{$callback[1]}($arrFields, $formId, $this);
			}
		}

		// Process the fields
		if (!empty($arrFields) && \is_array($arrFields))
		{
			foreach ($arrFields as $objField)
			{
				/** @var FormFieldModel $objField */
				$strClass = $GLOBALS['TL_FFL'][$objField->type] ?? null;

				// Continue if the class is not defined
				if (!class_exists($strClass))
				{
					continue;
				}

				$arrData = $objField->row();

				$arrData['decodeEntities'] = true;
				$arrData['allowHtml'] = $this->allowTags;

				// Submit buttons do not use the name attribute
				if ($objField->type == 'submit')
				{
					$arrData['name'] = '';
				}

				// Unset the default value depending on the field type (see #4722)
				if (!empty($arrData['value']) && !\in_array('value', StringUtil::trimsplit('[,;]', $GLOBALS['TL_DCA']['tl_form_field']['palettes'][$objField->type] ?? '')))
				{
					$arrData['value'] = '';
				}

				/** @var Widget $objWidget */
				$objWidget = new $strClass($arrData);
				$objWidget->required = $objField->mandatory ? true : false;

				// HOOK: load form field callback
				if (isset($GLOBALS['TL_HOOKS']['loadFormField']) && \is_array($GLOBALS['TL_HOOKS']['loadFormField']))
				{
					foreach ($GLOBALS['TL_HOOKS']['loadFormField'] as $callback)
					{
						$this->import($callback[0]);
						$objWidget = $this->{$callback[0]}->{$callback[1]}($objWidget, $formId, $this->arrData, $this);
					}
				}

				// Validate the input
				if (Input::post('FORM_SUBMIT') == $formId)
				{
					$objWidget->validate();

					// HOOK: validate form field callback
					if (isset($GLOBALS['TL_HOOKS']['validateFormField']) && \is_array($GLOBALS['TL_HOOKS']['validateFormField']))
					{
						foreach ($GLOBALS['TL_HOOKS']['validateFormField'] as $callback)
						{
							$this->import($callback[0]);
							$objWidget = $this->{$callback[0]}->{$callback[1]}($objWidget, $formId, $this->arrData, $this);
						}
					}

					if ($objWidget->hasErrors())
					{
						$doNotSubmit = true;
					}
					elseif ($objWidget->submitInput())
					{
						$arrSubmitted[$objField->name] = $objWidget->value;
						Input::setPost($objField->name, null); // see #5474
					}
				}

				if ($objWidget instanceof UploadableWidgetInterface)
				{
					if ($objWidget->value)
					{
						$arrFiles[$objField->name] = $objWidget->value;
					}

					$hasUpload = true;
				}

				if ($objWidget instanceof FormHidden)
				{
					$this->Template->hidden .= $objWidget->parse();
					continue;
				}

				if ($objWidget->name && $objWidget->label)
				{
					$arrLabels[$objWidget->name] = System::getContainer()->get('contao.insert_tag.parser')->replaceInline($objWidget->label); // see #4268
				}

				$this->Template->fields .= $objWidget->parse();
			}
		}

		// Process the form data
		if (!$doNotSubmit && Input::post('FORM_SUBMIT') == $formId)
		{
			$this->processFormData($arrSubmitted, $arrLabels, $arrFields, $arrFiles);
		}

		// Remove any uploads, if form did not validate (#1185)
		if ($doNotSubmit && $hasUpload)
		{
			foreach ($arrFiles as $upload)
			{
				if (!empty($upload['uuid']) && null !== ($file = FilesModel::findById($upload['uuid'])))
				{
					$file->delete();
				}

				if (is_file($upload['tmp_name']))
				{
					unlink($upload['tmp_name']);
				}
			}
		}

		// Add a warning to the page title
		if (
			$doNotSubmit
			&& !Environment::get('isAjaxRequest')
			&& ($responseContext = System::getContainer()->get('contao.routing.response_context_accessor')->getResponseContext())
			&& $responseContext->has(HtmlHeadBag::class)
		) {
			/** @var HtmlHeadBag $htmlHeadBag */
			$htmlHeadBag = $responseContext->get(HtmlHeadBag::class);
			$htmlHeadBag->setTitle($GLOBALS['TL_LANG']['ERR']['form'] . ' - ' . $htmlHeadBag->getTitle());
		}

		$strAttributes = '';
		$arrAttributes = StringUtil::deserialize($this->attributes, true);

		if (!empty($arrAttributes[0]))
		{
			$strAttributes .= ' id="' . $arrAttributes[0] . '"';
		}

		if (!empty($arrAttributes[1]))
		{
			$strAttributes .= ' class="' . $arrAttributes[1] . '"';
		}

		$this->Template->hasError = $doNotSubmit;
		$this->Template->attributes = $strAttributes;
		$this->Template->enctype = $hasUpload ? 'multipart/form-data' : 'application/x-www-form-urlencoded';
		$this->Template->maxFileSize = $hasUpload ? $this->objModel->getMaxUploadFileSize() : false;
		$this->Template->novalidate = $this->novalidate ? ' novalidate' : '';

		// Get the target URL
		if ($this->method == 'GET' && ($objTarget = $this->objModel->getRelated('jumpTo')) instanceof PageModel)
		{
			/** @var PageModel $objTarget */
			$this->Template->action = $objTarget->getFrontendUrl();
		}
	}

	/**
	 * Process form data, store it in the session and redirect to the jumpTo page
	 *
	 * @param array $arrSubmitted
	 * @param array $arrLabels
	 * @param array $arrFields
	 * @param array $arrFiles
	 */
	protected function processFormData($arrSubmitted, $arrLabels, $arrFields, $arrFiles)
	{
		// HOOK: prepare form data callback
		if (isset($GLOBALS['TL_HOOKS']['prepareFormData']) && \is_array($GLOBALS['TL_HOOKS']['prepareFormData']))
		{
			foreach ($GLOBALS['TL_HOOKS']['prepareFormData'] as $callback)
			{
				$this->import($callback[0]);
				$this->{$callback[0]}->{$callback[1]}($arrSubmitted, $arrLabels, $arrFields, $this);
			}
		}

		// Store submitted data (possibly modified by hook or data added) in the session for 10 seconds,
		// so it can be used on any forward page using the {{form_session_data::<form-field-name>}} insert tag
		System::getContainer()->get('request_stack')->getSession()->set(self::SESSION_KEY, new AutoExpiringAttribute(10, $arrSubmitted));

		// Send form data via e-mail
		if ($this->sendViaEmail)
		{
			$keys = array();
			$values = array();
			$fields = array();
			$message = '';

			foreach ($arrSubmitted as $k=>$v)
			{
				if ($k == 'cc')
				{
					continue;
				}

				$v = StringUtil::deserialize($v);

				// Skip empty fields
				if ($this->skipEmpty && !\is_array($v) && !\strlen($v))
				{
					continue;
				}

				// Add field to message
				$message .= ($arrLabels[$k] ?? ucfirst($k)) . ': ' . (\is_array($v) ? implode(', ', $v) : $v) . "\n";

				// Prepare XML file
				if ($this->format == 'xml')
				{
					$fields[] = array
					(
						'name' => $k,
						'values' => (\is_array($v) ? $v : array($v))
					);
				}

				// Prepare CSV file
				if ($this->format == 'csv' || $this->format == 'csv_excel')
				{
					$keys[] = $k;
					$values[] = (\is_array($v) ? implode(',', $v) : $v);
				}
			}

			$recipients = StringUtil::splitCsv($this->recipient);

			// Format recipients
			foreach ($recipients as $k=>$v)
			{
				$recipients[$k] = str_replace(array('[', ']', '"'), array('<', '>', ''), $v);
			}

			$email = new Email();

			// Get subject and message
			if ($this->format == 'email')
			{
				$message = $arrSubmitted['message'] ?? '';
				$email->subject = $arrSubmitted['subject'] ?? '';
			}

			// Set the admin e-mail as "from" address
			$email->from = $GLOBALS['TL_ADMIN_EMAIL'];
			$email->fromName = $GLOBALS['TL_ADMIN_NAME'];

			// Get the "reply to" address
			if (!empty(Input::post('email', true)))
			{
				$replyTo = Input::post('email', true);

				// Add the name
				if (!empty(Input::post('name')))
				{
					$replyTo = '"' . Input::post('name') . '" <' . $replyTo . '>';
				}
				elseif (!empty(Input::post('firstname')) && !empty(Input::post('lastname')))
				{
					$replyTo = '"' . Input::post('firstname') . ' ' . Input::post('lastname') . '" <' . $replyTo . '>';
				}

				$email->replyTo($replyTo);
			}

			// Fallback to default subject
			if (!$email->subject)
			{
				$email->subject = html_entity_decode(System::getContainer()->get('contao.insert_tag.parser')->replaceInline($this->subject), ENT_QUOTES, 'UTF-8');
			}

			// Send copy to sender
			if (!empty($arrSubmitted['cc']))
			{
				$email->sendCc(Input::post('email', true));
			}

			// Attach XML file
			if ($this->format == 'xml')
			{
				$objTemplate = new FrontendTemplate('form_xml');
				$objTemplate->fields = $fields;
				$objTemplate->charset = System::getContainer()->getParameter('kernel.charset');

				$email->attachFileFromString($objTemplate->parse(), 'form.xml', 'application/xml');
			}

			// Attach CSV file
			if ($this->format == 'csv')
			{
				$email->attachFileFromString(StringUtil::decodeEntities('"' . implode('";"', $keys) . '"' . "\n" . '"' . implode('";"', $values) . '"'), 'form.csv', 'text/comma-separated-values');
			}
			elseif ($this->format == 'csv_excel')
			{
				$email->attachFileFromString(mb_convert_encoding("\u{FEFF}sep=;\n" . StringUtil::decodeEntities('"' . implode('";"', $keys) . '"' . "\n" . '"' . implode('";"', $values) . '"'), 'UTF-16LE', 'UTF-8'), 'form.csv', 'text/comma-separated-values');
			}

			$uploaded = '';

			// Attach uploaded files
			if (!empty($arrFiles))
			{
				foreach ($arrFiles as $file)
				{
					// Add a link to the uploaded file
					if (isset($file['uploaded']))
					{
						$uploaded .= "\n" . Environment::get('base') . StringUtil::stripRootDir(\dirname($file['tmp_name'])) . '/' . rawurlencode($file['name']);
						continue;
					}

					$email->attachFileFromString(file_get_contents($file['tmp_name']), $file['name'], $file['type']);
				}
			}

			$uploaded = trim($uploaded) ? "\n\n---\n" . $uploaded : '';
			$email->text = StringUtil::decodeEntities(trim($message)) . $uploaded . "\n\n";

			// Set the transport
			if (!empty($this->mailerTransport))
			{
				$email->addHeader('X-Transport', $this->mailerTransport);
			}

			// Send the e-mail
			$email->sendTo($recipients);
		}

		// Store the values in the database
		if ($this->storeValues && $this->targetTable)
		{
			$arrSet = array();

			// Add the timestamp
			if ($this->Database->fieldExists('tstamp', $this->targetTable))
			{
				$arrSet['tstamp'] = time();
			}

			// Fields
			foreach ($arrSubmitted as $k=>$v)
			{
				if ($k != 'cc' && $k != 'id')
				{
					$arrSet[$k] = $v;

					// Convert date formats into timestamps (see #6827)
					if ($arrSet[$k] && \in_array($arrFields[$k]->rgxp, array('date', 'time', 'datim')))
					{
						$objDate = new Date($arrSet[$k], Date::getFormatFromRgxp($arrFields[$k]->rgxp));
						$arrSet[$k] = $objDate->tstamp;
					}
				}
			}

			// Files
			if (!empty($arrFiles))
			{
				foreach ($arrFiles as $k=>$v)
				{
					if ($v['uploaded'] ?? null)
					{
						$arrSet[$k] = StringUtil::stripRootDir($v['tmp_name']);
					}
				}
			}

			// HOOK: store form data callback
			if (isset($GLOBALS['TL_HOOKS']['storeFormData']) && \is_array($GLOBALS['TL_HOOKS']['storeFormData']))
			{
				foreach ($GLOBALS['TL_HOOKS']['storeFormData'] as $callback)
				{
					$this->import($callback[0]);
					$arrSet = $this->{$callback[0]}->{$callback[1]}($arrSet, $this);
				}
			}

			// Load DataContainer of target table before trying to determine empty value (see #3499)
			Controller::loadDataContainer($this->targetTable);

			// Set the correct empty value (see #6284, #6373)
			foreach ($arrSet as $k=>$v)
			{
				if ($v === '')
				{
					$arrSet[$k] = Widget::getEmptyValueByFieldType($GLOBALS['TL_DCA'][$this->targetTable]['fields'][$k]['sql'] ?? array());
				}
			}

			// Do not use Models here (backwards compatibility)
			$this->Database->prepare("INSERT INTO " . $this->targetTable . " %s")->set($arrSet)->execute();
		}

		// HOOK: process form data callback
		if (isset($GLOBALS['TL_HOOKS']['processFormData']) && \is_array($GLOBALS['TL_HOOKS']['processFormData']))
		{
			foreach ($GLOBALS['TL_HOOKS']['processFormData'] as $callback)
			{
				$this->import($callback[0]);
				$this->{$callback[0]}->{$callback[1]}($arrSubmitted, $this->arrData, $arrFiles, $arrLabels, $this);
			}
		}

		// Add a log entry
		if (System::getContainer()->get('contao.security.token_checker')->hasFrontendUser())
		{
			$this->import(FrontendUser::class, 'User');

			System::getContainer()->get('monolog.logger.contao.forms')->info('Form "' . $this->title . '" has been submitted by "' . $this->User->username . '".');
		}
		else
		{
			System::getContainer()->get('monolog.logger.contao.forms')->info('Form "' . $this->title . '" has been submitted by a guest.');
		}

		// Check whether there is a jumpTo page
		if (($objJumpTo = $this->objModel->getRelated('jumpTo')) instanceof PageModel)
		{
			$this->jumpToOrReload($objJumpTo->row());
		}

		$this->reload();
	}
}
