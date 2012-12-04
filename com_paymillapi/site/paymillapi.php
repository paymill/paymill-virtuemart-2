<?php
// no direct access
defined('_JEXEC') or die('Restricted access');

//Load controller file once
require_once(JPATH_COMPONENT.DS.'controller.php');

// Require specific controller if requested
if($controller = JRequest::getWord('controller')) {
    $path = JPATH_COMPONENT.DS.'controllers'.DS.$controller.'.php';
    if (file_exists($path)) {
        require_once $path;
    } else {
        $controller = '';
    }
}

//Create controller
$classname = 'PaymillapiController'.$controller;
$controller = new $classname();

//Perform task in URL
$controller->execute(JRequest::getWord('task'));

//Redirect if set by controller
$controller->redirect();

?>