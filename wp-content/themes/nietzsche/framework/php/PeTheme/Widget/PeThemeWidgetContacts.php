<?php

class PeThemeWidgetContacts extends PeThemeWidget {

	public function __construct() {
		$this->name = __("Pixelentity - Contacts",'nietzsche');
		$this->description = __("Statistical informations and links",'nietzsche');
		$this->wclass = "widget_contact";

		$this->fields = array(
							  "title" => 
							  array(
									"label"=>__("Title",'nietzsche'),
									"type"=>"Text",
									"description" => __("Widget title",'nietzsche'),
									"default"=>"Contact Widget"
									),
							  "address_icon"=> 
							  array(
									"label" => __("Address Icon",'nietzsche'),
									"type" => "Select",
									"description" => __("Select icon type. See the help documentation for a link to the list of available icons",'nietzsche'),
									"single" => true,
									"options" => PeGlobal::$const->data->icons,
									"default" => "icon-map-marker"
									),
							  "address_content" => 
							  array(
									"label"=>__("Address",'nietzsche'),
									"type"=>"TextArea",
									"description" => __("Address box content.",'nietzsche'),
									"default"=>sprintf('<strong>Mentor Business</strong><br>%s24 Street Name<br>%sCity Name<br>%sCountry Name',"\n","\n","\n")
									),
							  
							  "email_icon"=> 
							  array(
									"label" => __("Email Icon",'nietzsche'),
									"type" => "Select",
									"description" => __("Select icon type. See the help documentation for a link to the list of available icons",'nietzsche'),
									"single" => true,
									"options" => PeGlobal::$const->data->icons,
									"default" => "icon-envelope"
									),
							  "email_content" => 
							  array(
									"label"=>__("Email",'nietzsche'),
									"type"=>"TextArea",
									"description" => __("Email box content.",'nietzsche'),
									"default"=>sprintf('<a href="mailto:your@email.com">%syour@email.com%s</a><br>',"\n","\n")
									),

							  "phone_icon"=> 
							  array(
									"label" => __("Phone Icon",'nietzsche'),
									"type" => "Select",
									"description" => __("Select icon type. See the help documentation for a link to the list of available icons",'nietzsche'),
									"single" => true,
									"options" => PeGlobal::$const->data->icons,
									"default" => "icon-info-sign"
									),
							  "phone_content" => 
							  array(
									"label"=>__("Phone",'nietzsche'),
									"type"=>"TextArea",
									"description" => __("Phone box content.",'nietzsche'),
									"default"=>sprintf('+0044 123 4567 890')
									),

							  "vcard_icon"=> 
							  array(
									"label" => __("Vcard Icon",'nietzsche'),
									"type" => "Select",
									"description" => __("Select icon type. See the help documentation for a link to the list of available icons",'nietzsche'),
									"single" => true,
									"options" => PeGlobal::$const->data->icons,
									"default" => "icon-user"
									),
							  "vcard_content" => 
							  array(
									"label"=>__("Vcard",'nietzsche'),
									"type"=>"TextArea",
									"description" => __("Vcard box content.",'nietzsche'),
									"default"=>sprintf('<a href="#">Mentor vcard</a>')
									),

							  "hours_icon"=> 
							  array(
									"label" => __("Opening Hours Icon",'nietzsche'),
									"type" => "Select",
									"description" => __("Select icon type. See the help documentation for a link to the list of available icons",'nietzsche'),
									"single" => true,
									"options" => PeGlobal::$const->data->icons,
									"default" => "icon-user"
									),
							  "hours_content" => 
							  array(
									"label"=>__("Opening Hours",'nietzsche'),
									"type"=>"TextArea",
									"description" => __("Opening hours box content.",'nietzsche'),
									"default"=>sprintf('Mon-Fri: 9:00 &rarr; 18:00<br/>%sSat: 10:00 &rarr; 17:00<br/>%sSun: Closed',"\n","\n")
									)
							  
							  );

		parent::__construct();
	}

	public function &getContent(&$instance) {
		extract($instance);

		$html = "";

		if (isset($title)) {
			$html .= "<h3>$title</h3>";
		}

		if (isset($address_content) && $address_content) {
			$html .= '<div class="address">';
			$html .= sprintf('<span class="%s"></span>',$address_icon);
			$html .= sprintf('<p>%s</p>',$address_content);
			$html .= '</div>';
		}

		if (isset($email_content) && $email_content) {
			$html .= '<div class="email">';
			$html .= sprintf('<span class="%s"></span>',$email_icon);
			$html .= sprintf('<p>%s</p>',$email_content);
			$html .= '</div>';
		}

		if (isset($phone_content) && $phone_content) {
			$html .= '<div class="phone">';
			$html .= sprintf('<span class="%s"></span>',$phone_icon);
			$html .= sprintf('<p>%s</p>',$phone_content);
			$html .= '</div>';
		}

		if (isset($vcard_content) && $vcard_content) {
			$html .= '<div class="vcard">';
			$html .= sprintf('<span class="%s"></span>',$vcard_icon);
			$html .= sprintf('<p>%s</p>',$vcard_content);
			$html .= '</div>';
		}

		if (isset($hours_content) && $hours_content) {
			$html .= '<div class="hours">';
			$html .= sprintf('<span class="%s"></span>',$hours_icon);
			$html .= sprintf('<p class="hours">%s</p>',$hours_content);
			$html .= '</div>';
		}

		return $html;
	}


}
?>
