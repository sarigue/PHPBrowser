<?php
/**
 * Gestion d'un formulaire.
 * - Parse le DOM passé en paramètre
 * - Permet de définir une valeur ou plusieurs valeurs à un nom 
 * - Permet de soumettre le formulaire à l'aide du navigateur passé en paramètre du constructeur
 *  
 * @author Francois RAOULT
 * 
 * @license GNU/LGPL
 *
 */

class Form
{

	/**
	 * @var DOMElement
	 */
	protected $form    = NULL;

	/**
	 * @var Browser
	 */
	protected $browser = NULL;

	/**
	 * @var array
	 */
	protected $data    = array();
	
	/**
	 * 
	 * @param DOMElement $form
	 * @param Browser $browser
	 */
	public function __construct(DOMElement $form, Browser $browser)
	{
		$this->form    = $form;
		$this->browser = $browser;
		
		$this->reset();
		
	}
	
	/**
	 * Destructeur
	 */
	public function __destruct()
	{
	}

	/**
	 * @return string
	 */
	public function getAction()
	{
		return $this->form->getAttribute('action');
	}

	/**
	 * @return string
	 */
	public function getMethod()
	{
		return $this->form->getAttribute('method');
	}

	/**
	 * @return string
	 */
	public function getEnctype()
	{
		return $this->form->getAttribute('enctype');
	}

	/**
	 * @return string
	 */
	public function getId()
	{
		return $this->form->getAttribute('id');
	}

	/**
	 * @return string
	 */
	public function getName()
	{
		return $this->form->getAttribute('name');
	}
	
	/**
	 * @return DOMElement
	 */
	public function getFormElement()
	{
		return $this->form;
	}
	
	/**
	 * @return Browser4
	 */
	public function getBrowser()
	{
		return $this->browser;
	}
	
	/**
	 * @return array
	 */
	public function getData()
	{
		return $this->data;
	}
	
	/**
	 * "Coche" un champ (equivalent de "setData" avec "on" en valeur : valeur donné pour une checkbox)
	 * @param string $name
	 * @return Form
	 */
	public function checkField($name)
	{
		return $this->setData($name, 'on');
	}
	
	/**
	 * "Décoche" un champ (equivalent de "removeData")
	 * @param string $name
	 * @return Form
	 */
	public function uncheckField($name)
	{
		$this->removeData($name);
	}
	
	/**
	 * Définit une valeur (même avec une valeur vide)
	 * @param string $name
	 * @param string $value
	 * @return Form
	 */
	public function setData($name, $value)
	{
		$this->data[$name] = $value;
		return $this;
	}

	/**
	 * Supprime une valeur
	 * @param string $name
	 * @return Form
	 */
	public function removeData($name)
	{
		unset($this->data[$name]);
		return $this;
	}
	
	/**
	 * Réinitialise les données du formulaire
	 * @return Form
	 */
	public function reset()
	{
		$this->data = array();
				
		$checkbox_list = $this->_getElementList('input[@name!=""][@type="checkbox"][@checked]');
		for($i=0; $i < $checkbox_list->length;  $i++)
		{
			$checkbox = $checkbox_list->item($i); /* @var DOMElement $checkbox */
			$name     = $checkbox->getAttribute('name');
			$this->data[$name] = 'on';
		}
		
		$radio_list= $this->_getElementList('input[@name!=""][@type="radio"][@checked]');
		for($i=0; $i < $radio_list->length;  $i++)
		{
			$radio    = $radio_list->item($i); /* @var DOMElement $radio */
			$name     = $radio->getAttribute('name');
			$value    = $radio->getAttribute('value');
			$this->data[$name] = $value;
		}
		
		$text_list = $this->_getElementList('input[@name!=""][@type!="radio" and @type!="checkbox" and @type!="image" and @type!="submit" and @type!="reset"]');
		for($i=0; $i < $text_list->length;  $i++)
		{
			$text     = $text_list->item($i); /* @var DOMElement $text */
			$name     = $text->getAttribute('name');
			$value    = $text->getAttribute('value');
			$this->data[$name] = $value;
		}
		
		$domxpath = new DOMXPath($this->form->ownerDocument);
		
		$select_list = $this->_getElementList('select[@name!=""]');
		for($i=0; $i < $select_list->length;  $i++)
		{
			$select   = $select_list->item($i); /* @var DOMElement $select */
			$name     = $select->getAttribute('name');
			$this->data[$name] = array();
			$option_list = $domxpath->query('descendant::option[@selected]', $select);
			for($o=0; $o < $option_list->length; $o++)
			{
				$option = $option_list->item($o); /* @var DOMElement $option */
				$value  = $option->getAttribute('value');
				$this->data[$name][] = $value;
			}
		}
		
		$textarea_list = $this->_getElementList('textarea[@name!=""]');
		for($i=0; $i < $textarea_list->length;  $i++)
		{
			$textarea = $select_list->item($i); /* @var DOMElement $textarea */
			$name     = $textarea->getAttribute('name');
			$this->data[$name] = $textarea->textContent;
		}
		return $this;
	}
	
	/**
	 * Soumettre le formulaire, éventuellement en utilisant le bouton/image donné en paramètre
	 * @param string $nameOrId
	 * @return Browser
	 */
	public function submit($nameOrId=NULL)
	{
		$id   = NULL;
		$name = NULL;
		if (!empty($nameOrId) && strlen($nameOrId) > 1 && $nameOrId{0} == '#')
		{
			$id = substr($nameOrId, 1);
		}
		else
		{
			$name = $nameOrId;
		}

		$button_list = NULL; /* @var DOMNodeList $button_list */
		$button      = NULL; /* @var DOMElement  $button      */
		if ($id)
		{
			$button_list = $this->_getElementList('*[@id="'.$id.'"][@type="image" or @type="submit"]');
		}
		if ($name)
		{
			$button_list = $this->_getElementList('*[@name="'.$name.'"][@type="image" or @type="submit"]');
		}
		if ($button_list && $button_list->length > 0)
		{
			$button = $button_list->item(0);
		}
		elseif($nameOrId)
		{
			throw new Exception('Submission element '.$nameOrId.' not found !');
		}

		$action  = $this->form->getAttribute('action');
		$method  = $this->form->getAttribute('method');
		$enctype = $this->form->getAttribute('enctype');
		
		if ($button)
		{
			$name  = $button->getAttribute('name');
			$value = $button->getAttribute('value');
			$type  = $button->getAttribute('type');

			if ($button->hasAttribute('action'))
			{
				$action  = $button->getAttribute('action');
			}
			if ($button->hasAttribute('method'))
			{
				$method  = $button->getAttribute('method');
			}
			if ($button->hasAttribute('enctype'))
			{
				$enctype = $button->getAttribute('enctype');
			}
			
			if (strtolower($type) == 'image' && $name)
			{
				$this->data[$name.'.x'] = 1;
				$this->data[$name.'.y'] = 1;
			}
			elseif($name)
			{
				$this->data[$name] = $value;
			}
		}
				
		$this->browser->resetFormData();
		$this->browser->setFormDataList($this->data);
		$this->browser->sendData($action, $method, $enctype);
		return $this->browser;
	}
	
	/**
	 * Vérifie la présence d'un élément donné
	 * @param  string $elementNameOrId
	 * @return boolean
	 */
	public function hasElement($elementNameOrId)
	{
		if (strlen($elementNameOrId) > 1 && $elementNameOrId{0} == '#')
		{
			return $this->_getElementList('*[@id="'.substr($elementNameOrId, 1).'"]')->length > 0;
		}

		return $this->_getElementList('*[@name="'.$elementNameOrId.'"]')->length > 0;
		
	}
	
	
	/**
	 * Type d'un élément de formulaire
	 * @param string $elementNameOrId
	 * @return string
	 */
	public function getElementType($elementNameOrId)
	{
		$element_list = $this->_getElementList('*[@name="'.$elementNameOrId.'"]');
		if ($this->_getElementList('*[@name="'.$elementNameOrId.'"]')->length  == 0)
		{
			return NULL;
		}
		$domelement = $element_list->item(0); /* @var DOMElement $domelement */
		
		if (strtolower($domelement->nodeName) == 'textarea')
		{
			return 'textarea';
		}
		
		if (strtolower($domelement->nodeName) == 'select')
		{
			return 'select';
		}
		
		if (strtolower($domelement->nodeName) == 'option')
		{
			return 'select';
		}
		
		if (empty($domelement->getAttribute('type')))
		{
			return 'text';
		}

		return strtolower($domelement->getAttribute('type'));
		
	}
	
	/**
	 * @param string $xpath
	 * @return DOMNodeList
	 */
	private function _getElementList($xpath)
	{
		
		$formname = $this->form->getAttribute('name');
		
		$query = 'descendant::'.$xpath.($formname ?'|//'.$xpath.'[@form="'.$formname.'"]':'');
				
		$domxpath = new DOMXPath($this->form->ownerDocument);
		
		return $domxpath->query($query, $this->form);
	}
	
}