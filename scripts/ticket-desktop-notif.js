(function(window){
  'use strict';

  var config = window.ticketDesktopNotifConfig || {};
  var userId = parseInt(config.userId, 10);
  if(!userId || isNaN(userId)){ return; }
  if(window.ticketListPageHandlesNotifications){ return; }

  var $ = window.jQuery;
  if(!$){ return; }

  var notifSupport = ('Notification' in window);
  if(!notifSupport){ return; }

  var notifStorageKey = 'ticket_desktop_notif_' + userId;
  var ticketStateKey = 'ticket_notif_state_' + userId;
  var iconUrl = config.notificationIcon || '/gg_app/dist/img/sumlogo.png';
  var pollUrl = config.pollUrl || '/gg_app/pages/ticket/check_new_tickets.php';
  var pollInterval = config.pollInterval || 8000;
  var pollTimer = null;
  var isPolling = false;
  var lastTicketId = 0;
  var lastTicketUpdate = '';
  var storage = window.localStorage || null;

  hydrateTicketState();
  refreshPollingState();

  window.addEventListener('storage', function(evt){
    if(evt.key === notifStorageKey){
      refreshPollingState();
      return;
    }
    if(evt.key === ticketStateKey && evt.newValue){
      try {
        var snapshot = JSON.parse(evt.newValue);
        syncLocalState(snapshot);
      } catch(err) {}
    }
  });

  function hydrateTicketState(){
    if(!storage){ return; }
    try {
      var raw = storage.getItem(ticketStateKey);
      if(!raw){ return; }
      var parsed = JSON.parse(raw);
      if(parsed.latest_ticket_id !== undefined){
        var idVal = parseInt(parsed.latest_ticket_id, 10);
        if(!isNaN(idVal)){ lastTicketId = idVal; }
      }
      if(parsed.latest_ticket_update){
        lastTicketUpdate = parsed.latest_ticket_update;
      }
    } catch(err) {}
  }

  function syncLocalState(snapshot){
    if(!snapshot){ return; }
    if(snapshot.latest_ticket_id !== undefined){
      var idVal = parseInt(snapshot.latest_ticket_id, 10);
      if(!isNaN(idVal) && idVal > lastTicketId){
        lastTicketId = idVal;
      }
    }
    if(snapshot.latest_ticket_update){
      lastTicketUpdate = snapshot.latest_ticket_update;
    }
  }

  function shouldPoll(){
    if(window.ticketListPageHandlesNotifications){ return false; }
    if(Notification.permission !== 'granted'){ return false; }
    if(!storage){ return false; }
    return storage.getItem(notifStorageKey) === '1';
  }

  function refreshPollingState(){
    if(!notifSupport){ return; }
    if(window.ticketListPageHandlesNotifications){
      stopPolling();
      return;
    }
    if(shouldPoll()){
      startPolling();
    } else {
      stopPolling();
    }
  }

  function startPolling(){
    if(pollTimer || !shouldPoll()){ return; }
    pollTimer = window.setInterval(runPoll, pollInterval);
    runPoll();
  }

  function stopPolling(){
    if(pollTimer){
      window.clearInterval(pollTimer);
      pollTimer = null;
    }
  }

  function runPoll(){
    if(isPolling || !shouldPoll()){ return; }
    isPolling = true;

    $.ajax({
      url: pollUrl,
      method: 'POST',
      dataType: 'json',
      data: {
        last_ticket_id: lastTicketId || 0,
        last_ticket_update: lastTicketUpdate || ''
      },
      global: false
    }).done(handlePollResponse)
      .always(function(){ isPolling = false; });
  }

  function handlePollResponse(resp){
    if(!resp || !resp.success){ return; }
    var changed = false;
    var latestId = parseInt(resp.latest_ticket_id, 10);
    if(!isNaN(latestId) && latestId > lastTicketId){
      lastTicketId = latestId;
      changed = true;
    }
    if(resp.latest_ticket_update && resp.latest_ticket_update !== lastTicketUpdate){
      lastTicketUpdate = resp.latest_ticket_update;
      changed = true;
    }
    if(changed){
      persistTicketState();
    }
    if(resp.has_new && !isNaN(latestId) && latestId > 0){
      showDesktopNotification(resp.latest_ticket || null);
    }
  }

  function persistTicketState(){
    if(!storage){ return; }
    try {
      storage.setItem(ticketStateKey, JSON.stringify({
        latest_ticket_id: lastTicketId || 0,
        latest_ticket_update: lastTicketUpdate || ''
      }));
    } catch(err) {}
  }

  function showDesktopNotification(ticketInfo){
    if(Notification.permission !== 'granted'){ return; }
    var ticketNo = ticketInfo && ticketInfo.ticket_no ? ticketInfo.ticket_no : 'unknown';
    var dedupeKey = 'ticket_notif_shown_' + ticketNo;
    var now = Date.now();
    if(storage){
      var lastShown = parseInt(storage.getItem(dedupeKey) || '0', 10);
      if(lastShown && (now - lastShown) < 60000){
        return;
      }
      try { storage.setItem(dedupeKey, String(now)); } catch(err) {}
    }
    var title = ticketInfo && ticketInfo.ticket_no ? 'Ticket ' + ticketInfo.ticket_no : 'Ticket baru';
    var body = buildTicketNotificationBody(ticketInfo);
    try {
      var notification = new Notification(title, {
        body: body,
        icon: iconUrl,
        badge: iconUrl,
        tag: ticketInfo && ticketInfo.ticket_no ? 'ticket-' + ticketInfo.ticket_no : undefined,
        renotify: true
      });
      notification.onclick = function(){ window.focus(); notification.close(); };
    } catch(err) {}
  }

  function buildTicketNotificationBody(ticketInfo){
    var parts = [];
    if(ticketInfo && ticketInfo.subject){ parts.push(ticketInfo.subject); }
    if(ticketInfo && ticketInfo.creator_name){
      var line = 'Pemohon: ' + ticketInfo.creator_name;
      if(ticketInfo.latest_message){ line += ' · ' + ticketInfo.latest_message; }
      parts.push(line);
    }
    if(ticketInfo && ticketInfo.creator_dept){ parts.push('Dept: ' + ticketInfo.creator_dept); }
    return parts.join('\n') || 'Ticket baru berhasil dibuat.';
  }
})(window);
