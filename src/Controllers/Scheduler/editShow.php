<?php
/**
 * This is the magic form that makes URY actually have content - it enables users to apply for shows. And stuff.
 * 
 * @author Lloyd Wallis <lpw@ury.org.uk>
 * @version 21072012
 * @package MyURY_Scheduler
 */

//The Form definition
require 'Models/Scheduler/showfrm.php';
$form->setFieldValue('credits', User::getInstance()->getID())->setFieldValue('credittypes', 1);
require 'Views/Scheduler/createShow.php';