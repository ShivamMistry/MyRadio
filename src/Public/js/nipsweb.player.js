/**
 * This file contains the necessary functions for the NIPSWeb audio player
 */

manualSeek = true;
window.audioNodes = new Array();

function initialiseUI() {
  // Setup UI elements
  $('button.play').button({
    icons: {
      primary: 'ui-icon-play'
    },
    text: false
  }).addClass('ui-state-disabled');
  $('button.pause').button({
    icons: {
      primary: 'ui-icon-pause'
    },
    text: false
  }).addClass('ui-state-disabled');
  $('button.stop').button({
    icons: {
      primary: 'ui-icon-stop'
    },
    text: false
  }).addClass('ui-state-disabled');
  $('button.btn-logout').button({
    icons: {
      primary: 'ui-icon-power'
    },
    text: false
  });
  $('button.btn-help').button({
    icons: {
      primary: 'ui-icon-help'
    },
    text: false
  }).on('click', function() {
    window.open("?service=NIPSWeb&action=faq", "nwfaq", "status = 1, height = 800, width = 400, resizable = 1");
  });
  $('button.btn-feedback').button({
    icons: {
      primary: 'ui-icon-comment'
    }
  }).on('click', function() {
    window.open("/members/mail/send.php?listid=36", "nwfb", "status = 1, height = 600, width = 820, resizable = 1");
  });

  $('ul.baps-channel').sortable({
    //connectWith allows drag and drop between the channels
    connectWith: 'ul.baps-channel',
    //A distance dragged of 15 before entering the dragging state
    //Prevents accidentally dragging when clicking
    distance: 15,
    //Remove the "selected" class from the item - prevent multiple selected items in a channel
    //Also activate the next/previous item, if there is one
    remove: function(e, ui) {
      if (ui.item.hasClass('selected')) {
        ui.item.removeClass('selected');
        if (ui.item.nextSelect) ui.item.nextSelect.click();
      }
      ui.item.nextSelect = null;
    }
    
  });

  registerItemClicks();
  setupGenericListeners();
}

function initialisePlayer(channel) {

  if (channel == 0) channel = 'res';

  $("#progress-bar-" + channel).slider({
    range: "min",
    value: 0,
    min: 0
  });
  
  window.audioNodes[(channel === 'res') ? 0 : channel] = new Audio();

  setupListeners(channel);
}

// Initialises Variables for functions - This is called at the start of each function
function playerVariables(channel) {
  if (channel === 'res')
    channel = 0;
  return window.audioNodes[channel];
}



/**
 * Player Functions
 * @param channel 1, 2, 3 or res
 */
// Loads the selected track into the player for the designated channel
function previewLoad(channel) {
  $('#ch' + channel + '-play, #ch'+channel+'-pause, #ch'+channel+'-stop').addClass('ui-state-disabled');
  //Find the active track for this channel
  var audioid = $('#baps-channel-' + channel + ' li.selected').attr('id');
  var data = getRecTrackFromID(audioid);
  var type = $('#baps-channel-' + channel + ' li.selected').attr('type');
  if (type === 'central') {
    //Central Database Track
    $.ajax({
      url: '?service=NIPSWeb&action=create_token',
      type: 'post',
      data: 'trackid=' + data[1] + '&recordid=' + data[0],
      success: function() {
        playerVariables(channel).src = '?service=NIPSWeb&action=secure_play&recordid=' + data[0] + '&trackid=' + data[1];
        $(playerVariables(channel)).on("canplay", function() {
          $('#ch' + channel + '-play').removeClass('ui-state-disabled');
          /**
           * Briefly play the track once it has started loading
           * Workaround for http://code.google.com/p/chromium/issues/detail?id=111281
           */
          this.play();
          var that = this; //Hack so that timeout is in context
          this.volume = 0;
          setTimeout(function() {
            that.pause();
            that.volume = 1;
          }, 10);
        });
      }
    });
  } else if (type === 'aux') {
    playerVariables(channel).src = '?service=NIPSWeb&action=managed_play&managedid=' + $('#' + audioid).attr('managedid');
    $(playerVariables(channel)).on('canplay', function() {
      $('#ch' + channel + '-play').removeClass('ui-state-disabled');
    });
  }
}
// Plays the loaded track from the designated channel
function previewPlay(channel) {
  var audio = playerVariables(channel);

  audio.play();
  playing(channel);
}
// Pauses the currently playing track from the designated channel
function previewPause(channel) {
  var audio = playerVariables(channel);

  if (audio.readyState) {
    if (audio.paused) {
      audio.play();
      playing(channel);
    }
    else {
      audio.pause();
      pausing(channel);
    }
  }
}
// Stops the currently playing track from the designated channel
function previewStop(channel) {
  var audio = playerVariables(channel);

  audio.pause();
  audio.currentTime = 0;
  stopping(channel);
}

/**
 * UI Functions
 * @param channel 1, 2, 3 or res
 */
function playing(channel) {
  $('#ch' + channel + '-play').addClass('ui-state-active').removeClass('ui-state-disabled');
  $('#ch' + channel + '-pause').removeClass('ui-state-disabled');
  $('#ch' + channel + '-stop').removeClass('ui-state-disabled');
}
function pausing(channel) {
  $('#ch' + channel + '-play');
  $('#ch' + channel + '-pause').addClass('ui-state-active');
  $('#ch' + channel + '-stop');
}
function stopping(channel) {
  $('#ch' + channel + '-play').removeClass('ui-state-active');
  $('#ch' + channel + '-pause').removeClass('ui-state-active').addClass('ui-state-disabled');
  $('#ch' + channel + '-stop').addClass('ui-state-disabled');
}

// Gets the duration of the current track in channel
function getDuration(channel) {
  var audio = playerVariables(channel);

  var duration = audio.duration; //Get the duration of the track
  //duration returns a value in seconds. Convert to minutes+seconds, pad zeros where appropriate.
  var mindur = timeMins(duration);
  var secdur = timeSecs(duration);
  // Sets the duration label
  $('#ch' + channel + '-duration').html(mindur + ':' + secdur);
}
// Gets the time of the current track in channel
function getTime(channel) {
  var audio = playerVariables(channel);

  var elapsed = audio.currentTime; //Get the current playing position of the track
  //currentTime returns a value in seconds. Convert to minutes+seconds, pad zeros where appropriate.
  var minelap = timeMins(elapsed);
  var secelap = timeSecs(elapsed);
  // Sets the current time label
  $('#ch' + channel + '-elapsed').html(minelap + ':' + secelap);
}


/**
 * Event Listeners
 */

// Sets up generic listeners
function setupGenericListeners() {
  // Setup key bindings
  var keys = {
    F1: 112,
    F2: 113,
    F3: 114,
    F4: 115,
    F5: 116,
    F6: 117,
    F7: 118,
    F8: 119,
    F9: 120,
    F10: 121,
    F11: 122
  };
  // Sets up saving the database
  // - it could be bound to any of the channels but if you bind it to all 4 
  //   it'll trigger 4 times every time something changes
  $('#baps-channel-1').on('sortdeactivate', function() {
    updateState();
  });
  // Sets up key press triggers
  $(document).on('keydown.bapscontrol', function(e) {
    var trigger = false;
    switch (e.which) {
      case keys.F1:
        //Play channel 1
        previewPlay(1);
        trigger = true;
        break;
      case keys.F2:
        previewPause(1);
        trigger = true;
        break;
      case keys.F3:
        previewStop(1);
        trigger = true;
        break;
      case keys.F5:
        //Play channel 2
        previewPlay(2);
        trigger = true;
        break;
      case keys.F6:
        previewPause(2);
        trigger = true;
        break;
      case keys.F7:
        previewStop(2);
        trigger = true;
        break;
      case keys.F9:
        //Play channel 3
        previewPlay(3);
        trigger = true;
        break;
      case keys.F10:
        previewPause(3);
        trigger = true;
        break;
      case keys.F11:
        previewStop(3);
        trigger = true;
        break;
    }
    if (trigger) {
      e.stopPropagation();
      e.preventDefault();
      return false;
    }
  });
}
// Sets up listeners per channel
function setupListeners(channel) {
  var audio = playerVariables(channel);
  $(playerVariables(channel)).on('timeupdate', function() {
    getTime(channel);
    $('#progress-bar-' + channel).slider({value: audio.currentTime});
    //A mouse-over click doesn't set this properly on play
    if (audio.currentTime > 0.1) $('#ch' + channel + '-play').addClass('ui-state-active');
  });
  $('#progress-bar-' + channel).slider({
    value: 0,
    step: 0.01,
    orientation: "horizontal",
    range: "min",
    max: audio.duration,
    animate: true,
    stop: function(e, ui) {
      audio.currentTime = ui.value;
    }
  });
  $(playerVariables(channel)).on('durationchange', function() {
    getDuration(channel);
    $('#progress-bar-' + channel).slider({max: audio.duration});
  });

  $("#progress-bar-" + channel).on("slide", function(event, ui) {
    $('#previewer' + channel).currentTime = ui.value;
  });


}



/**
 * Generic Functions
 */
/**
 * Returns number of minutes (zero padded) from a time in seconds
 * @param time in seconds
 */
function timeMins(time) {
  var mins = Math.floor(time / 60) + "";
  if (mins.length < 2) {
    mins = '0' + mins;
  }
  return mins;
}
// Returns number of seconds (zero padded) less than mins from a time in seconds
function timeSecs(time) {
  var secs = Math.floor(time % 60) + "";
  if (secs.length < 2) {
    secs = '0' + secs;
  }
  return secs;
}
//Updates the JSON object with new item locations, then pushes this to the server
function updateState() {
  registerItemClicks();
  $('#notice').show();
  baps_state = {
    0: [],
    1: [],
    2: []
  };
  for (var i = 1; i < 4; i++) {
    $('#baps-channel-' + i + ' li').each(function() {
      var ids = getRecTrackFromID($(this).attr('id'));
      var data = {
        type: $(this).attr('type'),
        recordid: ids[0],
        trackid: ids[1]
      };

      if ($(this).attr('managedid') !== '') {
        data.managedid = $(this).attr('managedid');
      }

      baps_state[i - 1].push(data);
    });
  }
  $.ajax({
    url: 'ajax.php?action=save_state',
    type: 'post',
    data: baps_state,
    success: function() {
      $('#notice').hide();
    },
    error: function() {
      $('#notice').html('Error saving changes').addClass('ui-state-error');
    }
  });
}

function registerItemClicks() {
  // Used by dragdrop - enables the selected item to move down on drag/drop
  $('ul.baps-channel li').off('mousedown.predrag').on('mousedown.predrag', function(e) {
      this.nextSelect = $(this).next() ? $(this).next() || $(this).previous();
      console.log(this.nextSelect);
  });
  $('ul.baps-channel li').off('click.playactivator').on('click.playactivator', function(e) {
    if ($(this).hasClass('undigitised')) {
      //Can't select the track - it isn't digitised
      $('#footer-tips').html('The track ' + $(this).html() + ' has not been digitised.').show();
      setTimeout("$('#footer-tips').fadeOut();", 5000);
      e.stopPropagation();
      return false;
    }
    if ($(this).hasClass('unclean')) {
      //This track may have naughty words, but don't block selection
      $('#footer-tips').html('This track may contain restricted words. Use with caution.').show();
      setTimeout("$('#footer-tips').fadeOut();", 5000);
    }
    //Set this track as the active file for this channel
    //First, we need to remove the active class for any other file in the channel
    $(this).parent('ul').children().removeClass('selected');
    $(this).addClass('selected');
    previewLoad($(this).parent('.baps-channel').attr('channel'));
  });
  $('ul.baps-channel').tooltip({
    items: "li",
    content: function() {
      return $(this).html() + ' (' + $(this).attr('length') + ')';
    }
  });
}
function getRecTrackFromID(id) {
  id = id.split('-');

  var data = [];
  data[0] = id[0];
  data[1] = id[1];

  for (i = 2; i < id.length; i++) {
    data[1] = data[1] + '-' + id[i];
  }

  return data;
}
