<?php

/**
 * Provides the TracklistItem class for MyURY
 * @package MyURY_Tracklist
 */

/**
 * The Tracklist Item class provides information about URY's track playing
 * history.
 * 
 * @version 20130709
 * @author Lloyd Wallis <lpw@ury.org.uk>
 * @package MyURY_Tracklist
 * @uses \Database
 * 
 */
class MyURY_TracklistItem extends ServiceAPI {
  /**
   * The Singleton store for TracklistItem objects
   * @var MyURY_TracklistItem[]
   */
  private static $items = array();
  
  private $audiologid;
  private $source;
  private $starttime;
  private $endtime;
  private $state;
  private $timeslot;
  private $bapsaudioid;
  
  /**
   * MyURY_Track that was played, or an array of artist, album, track, label, length data.
   */
  private $track;
  
  private function __construct($id) {
    $this->audiologid = (int)$id;
    
    $result = self::$db->fetch_one('SELECT * FROM tracklist.tracklist
      LEFT JOIN tracklist.track_rec ON tracklist.audiologid = track_rec.audiologid
      LEFT JOIN tracklist.track_notrec ON tracklist.audiologid = track_notrec.audiologid
      WHERE tracklist.audiologid=$1 LIMIT 1');
    if (empty($result)) throw new MyURYException('The requested TracklistItem does not appear to exist!', 400);
    
    $this->source = $result['source'];
    $this->starttime = strtotime($result['timestart']);
    $this->endtime = strtotime($result['timestop']);
    $this->state = $result['state'];
    $this->timeslot = is_numeric($result['timeslotid']) ? MyURY_Timeslot::getInstance($result['timeslotid']) : null;
    $this->bapsaudioid = is_numeric($result['bapsaudioid']) ? (int)$result['bapsaudioid'] : null;
    
    $this->track = is_numeric($result['trackid']) ? MyURY_Track::getInstance($result['trackid']) :
      array(
          'artist' => $result['artist'],
          'album' => $result['album'],
          'label' => $result['label'],
          'trackno' => (int)$result['trackno'],
          'title' => $result['track'],
          'length' => $result['length']
      );
  }
  
  /**
   * Returns the current instance of that TracklistItem object if there is one, or runs the constructor if there isn't
   * @param int $audiologid The ID of the TracklistItem to return an object for
   * 
   * @return MyURY_TracklistItem
   */
  public static function getInstance($trackid = -1) {
    self::__wakeup();
    if (!is_numeric($trackid)) {
      throw new MyURYException('Invalid TracklistItem ID!', 400);
    }

    if (!isset(self::$items[$trackid])) {
      //See if there's one in the cache
      self::$items[$trackid] = new self($trackid);
    }

    return self::$items[$trackid];
  }
  
  /**
   * Returns an array of all TracklistItems played during the given Timeslot
   * @param MyURY_Timeslot $timeslot
   * @return Array
   */
  public static function getTracklistForTimeslot(MyURY_Timeslot $timeslot) {
    $result = self::$db->fetch_column('SELECT audiologid FROM tracklist.tracklist WHERE timeslotid=$1',
            array($timeslot->getID()));
    
    $items = array();
    foreach ($result as $item) {
      $items[] = self::getInstance($item);
    }
    
    return $items;
  }
  
  /**
   * Find all tracks played by Jukebox
   * @param int $start Period to start log from. Default 0.
   * @param int $end Period to end log from. Default time().
   */
  public static function getTracklistForJukebox($start = null, $end = null) {
    self::__wakeup();
    
    $start = $start === null ? '1970-01-01 00:00:00' : CoreUtils::getTimestamp($start);
    $end = $end === null ? CoreUtils::getTimestamp() : CoreUtils::getTimestamp($end);
    
    $result = self::$db->fetch_column('SELECT audiologid FROM tracklist.tracklist WHERE source=\'j\'
      AND timestart >= $1 AND timestart <= $2', array($start, $end));
    
    $items = array();
    foreach ($result as $item) {
      $items[] = self::getInstance($item);
    }
    
    return $items;
  }
  
  /**
   * Find all tracks played in the given timeframe
   * @param int $start Period to start log from. Required.
   * @param int $end Period to end log from. Default time().
   */
  public static function getTracklistForTime($start, $end = null) {
    self::__wakeup();
    
    $start = CoreUtils::getTimestamp($start);
    $end = $end === null ? CoreUtils::getTimestamp() : CoreUtils::getTimestamp($end);
    
    $result = self::$db->fetch_column('SELECT audiologid FROM tracklist.tracklist
      WHERE timestart >= $1 AND timestart <= $2', array($start, $end));
    
    $items = array();
    foreach ($result as $item) {
      $items[] = self::getInstance($item);
    }
    
    return $items;
  }
  
  /**
   * Takes as input a result set of num_plays and trackid, and generates the extended Datasource output used by
   * getTracklistStats(.*)()
   * @return Array, 2D, with the inner dimension being a MyURY_Track Datasource output, with the addition of:
   * num_plays: The number of times the track was played
   * total_playtime: The total number of seconds the track has been on air
   * in_playlists: A CSV of playlists the Track is in
   */
  private static function trackAmalgamator($result) {
    $data = array();
    foreach ($result as $row) {
      /**
       * @todo Temporary hack due to lack of fkey on tracklist.track_rec
       */
      try {
        $trackobj = MyURY_Track::getInstance($row['trackid']);
      } catch (MyURYException $e) {continue;}
      $track = $trackobj->toDataSource();
      $track['num_plays'] = $row['num_plays'];
      $track['total_playtime'] = $row['num_plays'] * $trackobj->getDuration();
      
      $playlistobjs = iTones_Playlist::getPlaylistsWithTrack($trackobj);
      $track['in_playlists'] = '';
      foreach ($playlistobjs as $playlist) {
        $track['in_playlists'] .= $playlist->getTitle().', ';
      }
      
      $data[] = $track;
    }
    return $data;
  }
  
  /**
   * Get an amalgamation of all tracks played by Jukebox. This looks at all played tracks within the proposed timeframe,
   * and outputs the play count of each Track, including the total time played.
   * @param int $start Period to start log from. Default 0.
   * @param int $end Period to end log from. Default time().
   * @return Array, 2D, with the inner dimension being a MyURY_Track Datasource output, with the addition of:
   * num_plays: The number of times the track was played
   * total_playtime: The total number of seconds the track has been on air
   * in_playlists: A CSV of playlists the Track is in
   */
  public static function getTracklistStatsForJukebox($start = null, $end = null) {
    self::__wakeup();
    
    $start = $start === null ? '1970-01-01 00:00:00' : CoreUtils::getTimestamp($start);
    $end = $end === null ? CoreUtils::getTimestamp() : CoreUtils::getTimestamp($end);
    
    $result = self::$db->fetch_all('SELECT COUNT(trackid) AS num_plays, trackid FROM tracklist.tracklist
      LEFT JOIN tracklist.track_rec ON tracklist.audiologid = track_rec.audiologid
      WHERE source=\'j\' AND timestart >= $1 AND timestart <= $2 AND trackid IS NOT NULL
      GROUP BY trackid ORDER BY num_plays DESC',
      array($start, $end));
    
    return self::trackAmalgamator($result);
  }
  
  /**
   * Get an amalgamation of all tracks played by BAPS. This looks at all played tracks within the proposed timeframe,
   * and outputs the play count of each Track, including the total time played.
   * @param int $start Period to start log from. Default 0.
   * @param int $end Period to end log from. Default time().
   * @return Array, 2D, with the inner dimension being a MyURY_Track Datasource output, with the addition of:
   * num_plays: The number of times the track was played
   * total_playtime: The total number of seconds the track has been on air
   * in_playlists: A CSV of playlists the Track is in
   */
  public static function getTracklistStatsForBAPS($start = null, $end = null) {
    self::__wakeup();
    
    $start = $start === null ? '1970-01-01 00:00:00' : CoreUtils::getTimestamp($start);
    $end = $end === null ? CoreUtils::getTimestamp() : CoreUtils::getTimestamp($end);
    
    $result = self::$db->fetch_all('SELECT COUNT(trackid) AS num_plays, trackid FROM tracklist.tracklist
      LEFT JOIN tracklist.track_rec ON tracklist.audiologid = track_rec.audiologid
      WHERE source=\'b\' AND timestart >= $1 AND timestart <= $2 AND trackid IS NOT NULL
      GROUP BY trackid ORDER BY num_plays DESC',
      array($start, $end));
    
    return self::trackAmalgamator($result);
  }
  
  /**
   * Returns if the given track has been played in the last $time seconds
   * 
   * @param MyURY_Track $track
   * @param int $time Optional. Default 10800 (3 hours)
   */
  public static function getIfPlayedRecently(MyURY_Track $track, $time = 10800) {
    $result = self::$db->fetch_column('SELECT timestart FROM tracklist.tracklist
      LEFT JOIN tracklist.track_rec ON tracklist.audiologid = track_rec.audiologid
      WHERE timestart >= $1 AND trackid = $2', array(CoreUtils::getTimestamp(time()-$time), $track->getID()));
    
    return sizeof($result) !== 0;
  }
}