<?php
/**
 * Main renderer for NIPSWeb
 * 
 * @author Lloyd Wallis <lpw@ury.org.uk>
 * @version 11032013
 * @package MyURY_NIPSWeb
 */
require 'Views/MyURY/bootstrap.php';

$twig->setTemplate('NIPSWeb/manage_library.twig')
        ->addVariable('reslists', CoreUtils::dataSourceParser(array(
            'managed' => array(),
            'aux' => NIPSWeb_ManagedPlaylist::getAllManagedPlaylists(true),
            'user' => NIPSWeb_ManagedUserPlaylist::getAllManagedUserPlaylistsFor(User::getInstance())
        )))
        ->render();