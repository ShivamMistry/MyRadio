<?php
CoreUtils::getTemplateObject(
)->setTemplate(
  'table.twig'
)->addVariable(
  'tablescript',
  'myury.datatable.default'
)->addVariable(
  'title',
  'Charts'
)->addVariable(
  'tabledata',
  ServiceAPI::setToDataSource(MyURY_ChartType::getAll())
)->render();
?>