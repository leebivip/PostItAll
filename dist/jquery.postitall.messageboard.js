const SERVER_URL = "../dist/messageboardController.php";

var guid = function() {
  function s4() {
    return Math.floor((1 + Math.random()) * 0x10000)
      .toString(16)
      .substring(1);
  }
  return s4() + s4() + '-' + s4() + '-' + s4() + '-' +
    s4() + '-' + s4() + s4() + s4();
}

// Cookies
function createCookie(name, value, days) {
    if (days) {
        var date = new Date();
        date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
        var expires = "; expires=" + date.toGMTString();
    }
    else var expires = "";

    //var fixedName = '<%= Request["formName"] %>';
    //name = fixedName + name;

    document.cookie = name + "=" + value + expires + "; path=/";
}

function readCookie(name) {
    var nameEQ = name + "=";
    var ca = document.cookie.split(';');
    for (var i = 0; i < ca.length; i++) {
        var c = ca[i];
        while (c.charAt(0) == ' ') c = c.substring(1, c.length);
        if (c.indexOf(nameEQ) == 0) return c.substring(nameEQ.length, c.length);
    }
    return null;
}

var getJsonRequest = function(params, callback) {

    var iduser = readCookie("iduser");
    if(iduser == null) {
        iduser = guid();
        createCookie("iduser", iduser, 365);
    }

    $.ajax({
        url: SERVER_URL + "?iduser=" + iduser, // + "&format=json",
        data: params,
        error: function(data) {
            console.log('An error has occurred', data);
        },
        success: function(data) {
            //console.log(data.message);
            if(data.status == "success") {
                if(callback != null) {
                    callback(data.message);
                }
            } else {
                $.fn.postitall.globals.errorMessage = 'An error has occurred' + data;
                console.log('An error has occurred' + data);
                if(callback != null) callback(null);
            }
        },
        type: 'POST'
    });

};

// External Storage Manager via AJAX
var externalManager = {
    test: function(callback) {
        getJsonRequest("option=test", function(retVal) {
            if(retVal !== null) {
                callback(true);
            } else {
                callback(false);
            }
        });
    },
    reload: function(callback, timeinterval) {
        var t = this;
        setInterval(function() {
            getJsonRequest("option=reload", function(reloadNotes) {
                if(reloadNotes) {
                    var res = reloadNotes.split("|");
                    if(res == "-1") {
                        //Reload all (delete)
                        $('#the_notes').remove();
                        $.PostItAll.load();
                    } else {
                        //New & update
                        $(res).each(function(i,e) {
                            var id = e.substring(e.indexOf("_") + 1);
                            t.get(id, function(varvalue) {
                                if($($.fn.postitall.globals.prefix + varvalue.id).length > 0) {
                                    //Update
                                    $($.fn.postitall.globals.prefix + varvalue.id).postitall('options', varvalue);
                                } else {
                                    if(e.substring(0,1) == "x") {
                                        //Delete
                                        $($.fn.postitall.globals.prefix + id).postitall('destroy');
                                        //TODO no funciona el destroy global!
                                        //$.PostItAll.destroy($.fn.postitall.globals.prefix + varvalue.id);
                                    } else {
                                        //New
                                        $.PostItAll.new(varvalue);
                                    }
                                }
                            });
                        });
                    }
                    callback(true);
                } else {
                    callback(false);
                }
            });
        }, timeinterval);
    },
    add: function(obj, callback) {
        console.log(obj);
        var varname = 'PostIt_' + parseInt(obj.id, 10);
        var testPrefs = encodeURIComponent(JSON.stringify(obj));
        //console.log('add', varname, testPrefs);
        getJsonRequest("option=add&key=" + varname + "&content=" + testPrefs, callback);
    },
    get: function(id, callback) {
        var varvalue;
        var varname = 'PostIt_' + parseInt(id, 10);
        getJsonRequest("option=get&key=" + varname, function(retVal) {
            try {
                varvalue = JSON.parse(retVal);
            } catch (e) {
                varvalue = "";
            }
            if(callback != null) callback(varvalue);
        });
    },
    getindex: function(callback) {
        var len = 0;
        var content = "";
        var paso = false;
        var t = this;
        t.getlengthTotalUser(function(len, lenUser) {
            var loadedItems = $('.PIApostit').length;
            var items = parseInt(len) + parseInt(loadedItems) + 1;
            //console.log('getIndex.len.items', len, items);
            for(var i = 1; i <= items; i++) {
                if(callback != null) {
                    (function(i) {
                        t.get(i, function(content) {
                            //console.log('getIndex.get', paso, i, content);
                            if(!paso && content == "" && $( "#idPostIt_" + i ).length <= 0) {
                                //console.log('nou index', i);
                                paso = true;
                            }
                            if(callback != null && (paso || i >= items)) {
                                callback(i);
                                callback = null;
                            }
                        });
                    })(i);
                }
            }
        });
    },
    remove: function(varname, callback) {
        //console.log('Remove',varname);
        varname = 'PostIt_' + parseInt(varname, 10);
        getJsonRequest("option=remove&key=" + varname, callback);
    },
    clear: function(callback) {
      var len = -1;
      var iteration = 0;
      var finded = false;
      var t = this;
      t.getlengthUser(function(len) {
          if(!len) {
              callback();
              return;
          }
          for (var i = 1; i <= len; i++) {
            t.key(i, function(key) {
              t.getByKey(key, function(o) {
                if(o != null) {
                    t.remove(o.id);
                }
                if(iteration == (len - 1) && callback != null) {
                    callback();
                    callback = null;
                }
                iteration++;
              });
            });
          }
      });
    },
    getlength: function(callback) {
        var total = 0;
        getJsonRequest("option=getlengthTotalUser", function(ret) {
            var res = ret.split("|");
            var total = ret[0];
            callback(total);
        });
    },
    getlengthUser: function(callback) {
        var total = 0;
        getJsonRequest("option=getLengthUser", function(total) {
            callback(total);
        });
    },
    getlengthTotal: function(callback) {
        var total = 0;
        getJsonRequest("option=getLengthTotal", function(total) {
            callback(total);
        });
    },
    getlengthTotalUser: function(callback) {
        getJsonRequest("option=getlengthTotalUser", function(ret) {
            var res = ret.split("|");
            callback(res[0], res[1]);
        });
    },
    key: function (i, callback) {
        i--;
        getJsonRequest("option=key&key="+i, function(retVal) {
            if(retVal)
                callback(retVal);
            else
                callback("");
        });
    },
    view: function () {
        console.log('view chrome');
        getJsonRequest("option=getByKey&key="+key, function(retVal) {
            console.log(retVal);
        });
    },
    getByKey: function (key, callback) {
          if (key != null && key.slice(0,7) === "PostIt_") {
              key = key.slice(7,key.length);
              getJsonRequest("option=getByKey&key="+key, callback);
          } else {
              if(callback != null) callback(null);
          }
    },
    getAll: function (callback) {
        getJsonRequest("option=getAll", callback);
    }
};
