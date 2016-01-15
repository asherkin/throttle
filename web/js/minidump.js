var StreamType = [];
StreamType[0] = 'MD_UNUSED_STREAM';
StreamType[1] = 'MD_RESERVED_STREAM_0';
StreamType[2] = 'MD_RESERVED_STREAM_1';
StreamType[3] = 'MD_THREAD_LIST_STREAM';
StreamType[4] = 'MD_MODULE_LIST_STREAM';
StreamType[5] = 'MD_MEMORY_LIST_STREAM';
StreamType[6] = 'MD_EXCEPTION_STREAM';
StreamType[7] = 'MD_SYSTEM_INFO_STREAM';
StreamType[8] = 'MD_THREAD_EX_LIST_STREAM';
StreamType[9] = 'MD_MEMORY_64_LIST_STREAM';
StreamType[10] = 'MD_COMMENT_STREAM_A';
StreamType[11] = 'MD_COMMENT_STREAM_W';
StreamType[12] = 'MD_HANDLE_DATA_STREAM';
StreamType[13] = 'MD_FUNCTION_TABLE_STREAM';
StreamType[14] = 'MD_UNLOADED_MODULE_LIST_STREAM';
StreamType[15] = 'MD_MISC_INFO_STREAM';
StreamType[16] = 'MD_MEMORY_INFO_LIST_STREAM';
StreamType[17] = 'MD_THREAD_INFO_LIST_STREAM';
StreamType[18] = 'MD_HANDLE_OPERATION_LIST_STREAM';
StreamType[0x0000ffff] = 'MD_LAST_RESERVED_STREAM';
StreamType[0x47670001] = 'MD_BREAKPAD_INFO_STREAM';
StreamType[0x47670002] = 'MD_ASSERTION_INFO_STREAM';
StreamType[0x47670003] = 'MD_LINUX_CPU_INFO';
StreamType[0x47670004] = 'MD_LINUX_PROC_STATUS';
StreamType[0x47670005] = 'MD_LINUX_LSB_RELEASE';
StreamType[0x47670006] = 'MD_LINUX_CMD_LINE';
StreamType[0x47670007] = 'MD_LINUX_ENVIRON';
StreamType[0x47670008] = 'MD_LINUX_AUXV';
StreamType[0x47670009] = 'MD_LINUX_MAPS';
StreamType[0x4767000A] = 'MD_LINUX_DSO_DEBUG';

var MinidumpFlags = [];
MinidumpFlags[0x00000000] = 'MD_NORMAL';
MinidumpFlags[0x00000001] = 'MD_WITH_DATA_SEGS';
MinidumpFlags[0x00000002] = 'MD_WITH_FULL_MEMORY';
MinidumpFlags[0x00000004] = 'MD_WITH_HANDLE_DATA';
MinidumpFlags[0x00000008] = 'MD_FILTER_MEMORY';
MinidumpFlags[0x00000010] = 'MD_SCAN_MEMORY';
MinidumpFlags[0x00000020] = 'MD_WITH_UNLOADED_MODULES';
MinidumpFlags[0x00000040] = 'MD_WITH_INDIRECTLY_REFERENCED_MEMORY';
MinidumpFlags[0x00000080] = 'MD_FILTER_MODULE_PATHS';
MinidumpFlags[0x00000100] = 'MD_WITH_PROCESS_THREAD_DATA';
MinidumpFlags[0x00000200] = 'MD_WITH_PRIVATE_READ_WRITE_MEMORY';
MinidumpFlags[0x00000400] = 'MD_WITHOUT_OPTIONAL_DATA';
MinidumpFlags[0x00000800] = 'MD_WITH_FULL_MEMORY_INFO';
MinidumpFlags[0x00001000] = 'MD_WITH_THREAD_INFO';
MinidumpFlags[0x00002000] = 'MD_WITH_CODE_SEGS';
MinidumpFlags[0x00004000] = 'MD_WITHOUT_AUXILLIARY_SEGS';
MinidumpFlags[0x00008000] = 'MD_WITH_FULL_AUXILLIARY_STATE';
MinidumpFlags[0x00010000] = 'MD_WITH_PRIVATE_WRITE_COPY_MEMORY';
MinidumpFlags[0x00020000] = 'MD_IGNORE_INACCESSIBLE_MEMORY';
MinidumpFlags[0x00040000] = 'MD_WITH_TOKEN_INFORMATION';

function hex(n) {
    return ('0000000' + ((n|0)+4294967296).toString(16)).substr(-8);
}

function hex64(n) {
    return (n.hi ? hex(n.hi) : '') + hex(n.lo);
}

function printMemory(name, view, hasBase) {
    var html = '';

    var base = null;
    if (hasBase) {
        base = view.getUint64();
        html += '<dt>' + name + ' Base</dt><dd>0x' + hex64(base) + '</dd>';
    }

    var size = view.getUint32()
    html += '<dt>' + name + ' Size</dt><dd>0x' + hex(size) + '</dd>';

    var offset = view.getUint32()
    //html += '<dt>' + name + ' Offset</dt><dd>0x' + hex(offset) + '</dd>';

    var reset = view.tell();
    view.seek(offset);

    var data = [];
    for (var i = 0; i < size; ++i) {
        data.push(view.getUint8());
    }

    view.seek(reset);

    var len = 16;

    html += '<div class="well well-sm clearfix" style="max-height: 300px; overflow-y: auto;">';

    if (hasBase) {
    html += '<pre style="float: left; border: none;">';
        for (var i = 0; i < size; ++i) {
            if ((i % len) === 0) {
                html += hex(base.lo + i) + '\n';
            }
        }
        html += '</pre>';
    }

    html += '<pre style="float: left; border: none;">';
    for (var i = 0; i < size; ++i) {
        html += (('00' + data[i].toString(16)).substr(-2)) + ' ';
        if ((i % len) === (len - 1)) {
            html += '\n';
        }
    }
    html += '</pre>';

    html += '<pre style="float: left; border: none;">';
    for (var i = 0; i < size; ++i) {
        var c = String.fromCharCode(data[i]).replace(/[^\x20-\x7E]+/, '.');
        switch (c) {
        case '&': c = '&amp;'; break;
        case '<': c = '&lt;'; break;
        case '>': c = '&gt;'; break;
        case '"': c = '&quot;'; break;
        case '\'': c = '&#039;'; break;
        }
        html += c;
        if ((i % len) === (len - 1)) {
            html += '\n';
        }
    }
    html += '</pre>';

    html += '</div>';

    return html;
}

var loading = $('#loading');
var progress = $('#loading .progress-bar');
var content = $('#content');

var oReq = new XMLHttpRequest();
oReq.open('GET', 'download', true);
oReq.responseType = 'arraybuffer';

oReq.onprogress = function(oEvent) {
    if (oEvent.lengthComputable) {
        progress.css('width', ((oEvent.loaded / oEvent.total) * 100) + '%');
    } else {
        progress.css('width', '100%');
        progress.addClass('active');
    }
};

oReq.onload = function(oEvent) {
    loading.remove();

    var arrayBuffer = oReq.response;
    if (!arrayBuffer) {
        return;
    }

    var view = new jDataView(arrayBuffer, 0, arrayBuffer.byteLength, true);

    var magic = view.getUint32();
    if (magic !== 1347241037) {
        return;
    }

    view.seek(0);
    var streamCount, streamDirectory;
    var html = '<dl class="dl-horizontal dl-minidump">';
    html += '<dt>Magic</dt><dd>' + view.getString(4) + '</dd>';
    html += '<dt>Version 1</dt><dd>' + view.getUint16() + '</dd>';
    html += '<dt>Version 2</dt><dd>' + view.getUint16() + '</dd>';
    html += '<dt>Stream Count</dt><dd>' + (streamCount = view.getUint32()) + '</dd>';
    html += '<dt>Stream Directory</dt><dd>0x' + hex(streamDirectory = view.getUint32()) + '</dd>';
    html += '<dt>Checksum</dt><dd>0x' + hex(view.getUint32()) + '</dd>';
    html += '<dt>Time Stamp</dt><dd>' + new Date(view.getUint32() * 1000) + '</dd>';
    var minidumpFlags = view.getUint64();
    html += '<dt>Flags</dt><dd>' + MinidumpFlags.filter(function(name, flag) { return (flag != 0 || minidumpFlags.lo == 0) && ((minidumpFlags.lo & flag) == flag); }).join(' | ') + ' (0x' + hex64(minidumpFlags) + ')</dd>';
    html += '</dl>';
    content.append(html);

    view.seek(streamDirectory);
    var streamType, streamSize, streamOffset;
    for (var i = 0; i < streamCount; ++i) {
        streamType = view.getUint32();
        if (StreamType[streamType]) {
            streamType = StreamType[streamType];
        }

        if (streamType === 'MD_UNUSED_STREAM') {
            continue;
        }

        html = '<div class="well"><dl class="dl-horizontal dl-minidump">';
        html += '<dt>Stream Type</dt><dd>' + streamType + '</dd>';
        streamSize = view.getUint32(); //html += '<dt>Stream Size</dt><dd>0x' + hex(streamSize = view.getUint32()) + '</dd>';
        streamOffset = view.getUint32(); //html += '<dt>Stream Offset</dt><dd>0x' + hex(streamOffset = view.getUint32()) + '</dd>';
        html += '</dl>';

        if (streamSize === 0) {
            html += '</div>';
            content.parent().append(html);
            continue;
        }

        var reset = view.tell();
        view.seek(streamOffset);

        html += '<hr><dl class="dl-horizontal dl-minidump">';

        switch (streamType) {
        case 'MD_THREAD_LIST_STREAM': {
            var threadCount;
            html += '<dt>Thread Count</dt><dd>' + (threadCount = view.getUint32()) + '</dd>';
            html += '</dl><dl>';
            for (var j = 0; j < threadCount; ++j) {
                html += '<dt>Thread ' + j + '</dt><dd><div class="well well-sm"><dl class="dl-horizontal dl-minidump">';
                html += '<dt>Thread ID</dt><dd>0x' + hex(view.getUint32()) + '</dd>';
                html += '<dt>Suspend Count</dt><dd>' + view.getUint32() + '</dd>';
                html += '<dt>Priority Class</dt><dd>0x' + hex(view.getUint32()) + '</dd>';
                html += '<dt>Priority</dt><dd>' + view.getInt32() + '</dd>';
                html += '<dt>TEB</dt><dd>0x' + hex64(view.getUint64()) + '</dd>';
                html += printMemory('Stack', view, true); 
                html += printMemory('Context', view, false);
                html += '</dl></div></dd>';
            }
            break;
        }
        case 'MD_MEMORY_LIST_STREAM': {
            var regionCount;
            html += '<dt>Region Count</dt><dd>' + (regionCount = view.getUint32()) + '</dd>';
            html += '</dl><dl>';
            for (var j = 0; j < regionCount; ++j) {
                html += '<dt>Region ' + j + '</dt><dd><div class="well well-sm"><dl class="dl-horizontal dl-minidump">';
                html += printMemory('Region', view, true);
                html += '</dl></div></dd>';
            }
            break;
        }
        case 'MD_BREAKPAD_INFO_STREAM': {
            html += '<dt>Validity</dt><dd>0x' + hex(view.getUint32()) + '</dd>';
            html += '<dt>Dump Thread ID</dt><dd>0x' + hex(view.getUint32()) + '</dd>';
            html += '<dt>Requesting Thread ID</dt><dd>0x' + hex(view.getUint32()) + '</dd>';
            break;
        }
        case 'MD_LINUX_CPU_INFO':
        case 'MD_LINUX_PROC_STATUS':
        case 'MD_LINUX_LSB_RELEASE':
        case 'MD_LINUX_MAPS': {
            html += '<pre>' + view.getString(streamSize - 1).replace(/\0/g, ' ') + '</pre>';
            break;
        }
        case 'MD_LINUX_CMD_LINE': {
            html += '<dt>Command Line</dt><dd>' + view.getString(streamSize - 1).replace(/\0/g, ' ') + '</dd>';
            break;
        }
        case 'MD_LINUX_ENVIRON': {
            var env = view.getString(streamSize - 1).split('\0');
            for (var j = 0; j < env.length; ++j) {
                var pair = env[j].split(/=(.+)/);
                html += '<dt>' + pair[0] + '</dt><dd>' + pair[1] + '</dd>';
            }
            break;
        }
        default:
            html += '<div class="alert alert-warning">This stream is currently unhandled.</div>';
        }

        html += '</dl>';
        view.seek(reset);

        html += '</div>';
        content.parent().append(html);
    }
};

oReq.send(null);
