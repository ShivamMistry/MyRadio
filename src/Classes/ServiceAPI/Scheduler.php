<?php
/**
 * Abstractor for the Scheduler Module
 *
 * @author lpw
 */
class Scheduler extends ServiceAPI {
  private static $pendingAllocationsResult = null;
  
  private static function pendingAllocationsQuery() {
    if (self::$pendingAllocationsResult === null) {
      self::$pendingAllocationsResult = self::$db->query('SELECT * FROM sched_entry WHERE entryid NOT IN
        (SELECT entryid FROM sched_timeslot)
        AND entryid NOT IN (SELECT entryid FROM sched_reject WHERE revokeddate IS NULL)');
    }
    
    return self::$pendingAllocationsResult;
  }
  
  /**
   * Returns the number of shows awaiting a timeslot allocation
   * @return int the number of pending shows 
   */
  public static function countPendingAllocations() {
    return (int)self::$db->num_rows(self::pendingAllocationsQuery());
  }
  
  /**
   * Returns all show requests awaiting a timeslot allocation
   * @return Array[Array] An array of arrays of shows pending allocation
   */
  public static function getPendingAllocations() {
    return self::$db->fetch_all(self::pendingAllocationsQuery());
  }
  
  /**
   * @todo implement this
   * @return int Zero. 
   */
  public static function countPendingDisputes() {
    return 0;
  }
  
  public static function getInstance($id = -1) {throw new MyURYException('Not Implemented');}
}
