<?php
/**
 * Main renderer for SIS
 * 
 * @author Andy Durant <aj@ury.org.uk>
 * @version 20130923
 * @package MyURY_SIS
 */

CoreUtils::requireTimeslot();

  $template = 'SIS/main.twig';
  $title = 'SIS';
  $plugins = SIS_Utils::getPlugins();
//  $tabs = SIS_Utils::getTabs();

var_dump($plugins);

CoreUtils::getTemplateObject()->setTemplate($template)
        ->addVariable('title', $title)
        ->addVariable('plugins', $plugins)
//        ->addVariable('tabs', $tabs)
        ->render();