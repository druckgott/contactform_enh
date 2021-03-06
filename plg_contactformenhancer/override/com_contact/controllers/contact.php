<?php

/**
 * This file is copied from components/com_contact/controllers/contact.php
 * Only the submit and _sendEmail functions are modified and some helper functions added
 * after it.
 *  
 * Created on October, 2013
 * Modified on April, 2016
 *
 * @package plg_system_contactformenhancer
 * @author Peter Macej
 * @copyright Copyright (c) 2016 Peter Macej. All rights reserved.
 * @license		GNU General Public License version 2 or later; see LICENSE.txt
 */  

/**
 * @package     Joomla.Site
 * @subpackage  com_contact
 *
 * @copyright   Copyright (C) 2005 - 2013 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

/**
 * Controller for single contact view.
 *
 * @package     Joomla.Site
 * @subpackage  com_contact
 */
class ContactControllerContact extends JControllerForm
{
	public function getModel($name = '', $prefix = '', $config = array('ignore_request' => true))
	{
		return parent::getModel($name, $prefix, array('ignore_request' => false));
	}


/**
 *******************************************************************************************
 * This is the modified part of the original components/com_contact/controllers/contact.php file
 * used in contactformenhancer plugin. 
 */


  /**
	 * Method to submit the contact form and send an email.
	 *
	 * @return  boolean  True on success sending the email. False on failure.
	 *
	 * @since   1.5.19
	 */
	public function submit()
	{
		// Check for request forgeries.
		JSession::checkToken() or jexit(JText::_('JINVALID_TOKEN'));
		$app    = JFactory::getApplication();
		$model  = $this->getModel('contact');
		$params = JComponentHelper::getParams('com_contact');
		$stub   = $this->input->getString('id');
		$id     = (int) $stub;

		// Get the data from POST
		$data  = $this->input->post->get('jform', array(), 'array');

		$contact = $model->getItem($id);

		$params->merge($contact->params);

		// Check for a valid session cookie
		if ($params->get('validate_session', 0))
		{
			if (JFactory::getSession()->getState() != 'active'){
				JError::raiseWarning(403, JText::_('COM_CONTACT_SESSION_INVALID'));

				// Save the data in the session.
				$app->setUserState('com_contact.contact.data', $data);

				// Redirect back to the contact form.
				$this->setRedirect(JRoute::_('index.php?option=com_contact&view=contact&id='.$stub, false));
				return false;
			}
		}

		// Contact plugins
		JPluginHelper::importPlugin('contact');
		$dispatcher	= JEventDispatcher::getInstance();

		// Validate the posted data.
		$form = $model->getForm();

		if (!$form)
		{
			JError::raiseError(500, $model->getError());
			return false;
		}

		$validate = $model->validate($form, $data);

		if ($validate === false)
		{
    
			// Get the validation messages.
			$errors	= $model->getErrors();
			// Push up to three validation messages out to the user.
			for ($i = 0, $n = count($errors); $i < $n && $i < 3; $i++)
			{
				if ($errors[$i] instanceof Exception)
				{
					$app->enqueueMessage($errors[$i]->getMessage(), 'warning');
				} else {
					$app->enqueueMessage($errors[$i], 'warning');
				}
			}

			// Save the data in the session.
			$app->setUserState('com_contact.contact.data', $data);

			// Redirect back to the contact form.
			$this->setRedirect(JRoute::_('index.php?option=com_contact&view=contact&id='.$stub, false));
			return false;
		}

		// Validation succeeded, continue with custom handlers
		$results	= $dispatcher->trigger('onValidateContact', array(&$contact, &$data));

		foreach ($results as $result)
		{
			if ($result instanceof Exception)
			{
				return false;
			}
		}

		// Passed Validation: Process the contact plugins to integrate with other applications
		$dispatcher->trigger('onSubmitContact', array(&$contact, &$data));

		// Send the email
		$sent = false;
		if (!$params->get('custom_reply'))
		{
			$sent = $this->_sendEmail($data, $contact, $params->get('show_email_copy'));
			
		}

		// Set the success message if it was a success
		if (!($sent instanceof Exception))
		{
			$msg = JText::_('COM_CONTACT_EMAIL_THANKS');
		}
		else
		{
			$msg = '';
		}

		// Flush the data from the session
		$app->setUserState('com_contact.contact.data', null);

		// Redirect if it is set in the parameters, otherwise redirect back to where we came from
		if ($contact->params->get('redirect'))
		{
			// get contactformenhancer plugin parameters
	    $plugin = JPluginHelper::getPlugin('system', 'contactformenhancer');
	    $pluginParams = new JRegistry();
	    $pluginParams->loadString($plugin->params);
			// supress "Thank you" message
			if ($pluginParams->get('supressThankYouMessage', '0') == '1') {
				$msg = null;
			}	    

			$this->setRedirect($contact->params->get('redirect'), $msg);
		}
		else
		{
			$this->setRedirect(JRoute::_('index.php?option=com_contact&view=contact&id='.$stub, false), $msg);
		}

		return true;
	}


  /**
	 *
	 * @param   array      $data                  The data to send in the email.
	 * @param   stdClass   $contact               The user information to send the email to
	 * @param   boolean    $copy_email_activated  True to send a copy of the email to the user.
	 *
	 * @return  boolean  True on success sending the email, false on failure.
	 *
	 * @since   1.6.4
	 */
	private function _sendEmail($data, $contact, $copy_email_activated)
	{
  			// get contactformenhancer plugin parameters
	    $plugin = JPluginHelper::getPlugin('system', 'contactformenhancer');
	    $pluginParams = new JRegistry();
	    $pluginParams->loadString($plugin->params);
	
			$app		= JFactory::getApplication();
			if ($contact->email_to == '' && $contact->user_id != 0)
			{
				$contact_user = JUser::getInstance($contact->user_id);
				$contact->email_to = $contact_user->get('email');
			}
			$mailfrom	= $app->get('mailfrom');
			$fromname	= $app->get('fromname');
			$sitename	= $app->get('sitename');

			$name		= $data['contact_name'];
			$email		= JStringPunycode::emailToPunycode($data['contact_email']);
			$subject	= $data['contact_subject'];
			$body		= $data['contact_message'];

			echo "das ist ein Test";
			// Prepare email body
			if ($pluginParams->get('allowBackslash', '1') == '1') {
				$body = str_replace("\\", "\\\\", $body);
			}
			
			$mail = JFactory::getMailer();
			if ($pluginParams->get('sendCustomEmail', '0') == '0') {
				// default original format
				$prefix = JText::sprintf('COM_CONTACT_ENQUIRY_TEXT', JUri::base());
				$body	= $prefix."\n".$name.' <'.$email.'>'."\r\n\r\n".stripslashes($body);
				$mail->addRecipient($contact->email_to);
				$mail->addReplyTo($email, $name);
				$mail->setSender(array($mailfrom, $fromname));
				$mail->setSubject($sitename.': '.$subject);
				$mail->setBody($body);
			} else {
				// custom format
				$body	= stripslashes($body);
				$mail->addRecipient($contact->email_to);

				$cReplyName = $pluginParams->get("customReplytoName", "");
				$cReplyName = $this->processVariables($cReplyName, $name, $email, $subject, $body);
				$cReplyEmail = $pluginParams->get("customReplytoEmail", "");
				$cReplyEmail = $this->processVariables($cReplyEmail, $name, $email, $subject, $body);
        if (trim($cReplyEmail) !== '') {
				  $mail->addReplyTo($cReplyEmail, $cReplyName);
        }

				$cFromName = $pluginParams->get("customFromName", "%FORM_NAME%");
				$cFromName = $this->processVariables($cFromName, $name, $email, $subject, $body);
				$cFromEmail = $pluginParams->get("customFromEmail", "%FORM_EMAIL%");
				$cFromEmail = $this->processVariables($cFromEmail, $name, $email, $subject, $body);
				$mail->setSender(array($cFromEmail, $cFromName));

				$cSubject = $pluginParams->get("customSubject", "%FORM_SUBJECT%");
				$cSubject = $this->processVariables($cSubject, $name, $email, $subject, $body);
				$mail->setSubject($cSubject);

				$cBody = $pluginParams->get("customMessage", "%FORM_MESSAGE%");
				$cBody = $this->processVariables($cBody, $name, $email, $subject, $body);
				$mail->setBody($cBody);
			}
			$sent = $mail->Send();

			//If we are supposed to copy the sender, do so.


      // Check whether email copy function activated}
			if ( (($copy_email_activated == true) && !empty($data['contact_email_copy']))	||  ($pluginParams->get('sendCustomCopyEmail_always', '0') == '1') )		{

				
				$copytext		= JText::sprintf('COM_CONTACT_COPYTEXT_OF', $contact->name, $sitename);
				$copytext		.= "\r\n\r\n".$body;
				$copysubject	= JText::sprintf('COM_CONTACT_COPYSUBJECT_OF', $subject);

				$mail = JFactory::getMailer();
				if ($pluginParams->get('sendCustomCopyEmail', '0') == '0') {
					// default original format
					$mail->addRecipient($email);
					$mail->addReplyTo($email, $name);
					$mail->setSender(array($mailfrom, $fromname));
					$mail->setSubject($copysubject);
					$mail->setBody($copytext);
				} else {
					// custom format
					$mail->addRecipient($email);
					$mail->addReplyTo($email, $name);
					$mail->setSender(array($mailfrom, $fromname));

					$cSubject = $pluginParams->get("customCopySubject", "Copy of: %FORM_SUBJECT%");
					$cSubject = $this->processVariables($cSubject, $name, $email, $subject, $body);
					$mail->setSubject($cSubject);

					$cBody = $pluginParams->get("customCopyMessage", "%FORM_MESSAGE%");
					$cBody = $this->processVariables($cBody, $name, $email, $subject, $body);
					$mail->setBody($cBody);
				}
				$sent = $mail->Send();
			}
			
		
		

			return $sent;
	}
	
	
	private function processVariables($text, $name, $email, $subject, $body) {
		$app		= JFactory::getApplication();
		$mailfrom	= $app->get('mailfrom');
		$fromname	= $app->get('fromname');
		$sitename	= $app->get('sitename');
		$siteurl = JUri::base();

		$text = str_replace("%FORM_SUBJECT%", $subject, $text);
		$text = str_replace("%FORM_NAME%", $name, $text);
		$text = str_replace("%FORM_EMAIL%", $email, $text);
		$text = str_replace("%FORM_MESSAGE%", $body, $text);
		$text = str_replace("%SITE_NAME%", $sitename, $text);
		$text = str_replace("%SITE_URL%", $siteurl, $text);
		$text = str_replace("%SITE_FROM_NAME%", $fromname, $text);
		$text = str_replace("%SITE_FROM_EMAIL%", $mailfrom, $text);
		
		return $text;
	}
}
