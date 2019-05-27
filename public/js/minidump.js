// https://chromium.googlesource.com/breakpad/breakpad/+/master/src/google_breakpad/common/minidump_format.h

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
StreamType[19] = 'MD_TOKEN_STREAM';
StreamType[20] = 'MD_JAVASCRIPT_DATA_STREAM';
StreamType[21] = 'MD_SYSTEM_MEMORY_INFO_STREAM';
StreamType[22] = 'MD_PROCESS_VM_COUNTERS_STREAM';
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

var AuxvNames = [];
AuxvNames[0] = 'AT_NULL';
AuxvNames[1] = 'AT_IGNORE';
AuxvNames[2] = 'AT_EXECFD';
AuxvNames[3] = 'AT_PHDR';
AuxvNames[4] = 'AT_PHENT';
AuxvNames[5] = 'AT_PHNUM';
AuxvNames[6] = 'AT_PAGESZ';
AuxvNames[7] = 'AT_BASE';
AuxvNames[8] = 'AT_FLAGS';
AuxvNames[9] = 'AT_ENTRY';
AuxvNames[10] = 'AT_NOTELF';
AuxvNames[11] = 'AT_UID';
AuxvNames[12] = 'AT_EUID';
AuxvNames[13] = 'AT_GID';
AuxvNames[14] = 'AT_EGID';
AuxvNames[17] = 'AT_CLKTCK';
AuxvNames[15] = 'AT_PLATFORM';
AuxvNames[16] = 'AT_HWCAP';
AuxvNames[18] = 'AT_FPUCW';
AuxvNames[19] = 'AT_DCACHEBSIZE';
AuxvNames[20] = 'AT_ICACHEBSIZE';
AuxvNames[21] = 'AT_UCACHEBSIZE';
AuxvNames[22] = 'AT_IGNOREPPC';
AuxvNames[23] = 'AT_SECURE';
AuxvNames[24] = 'AT_BASE_PLATFORM';
AuxvNames[25] = 'AT_RANDOM';
AuxvNames[26] = 'AT_HWCAP2';
AuxvNames[31] = 'AT_EXECFN';
AuxvNames[32] = 'AT_SYSINFO';
AuxvNames[33] = 'AT_SYSINFO_EHDR';
AuxvNames[34] = 'AT_L1I_CACHESHAPE';
AuxvNames[35] = 'AT_L1D_CACHESHAPE';
AuxvNames[36] = 'AT_L2_CACHESHAPE';
AuxvNames[37] = 'AT_L3_CACHESHAPE';
AuxvNames[40] = 'AT_L1I_CACHESIZE';
AuxvNames[41] = 'AT_L1I_CACHEGEOMETRY';
AuxvNames[42] = 'AT_L1D_CACHESIZE';
AuxvNames[43] = 'AT_L1D_CACHEGEOMETRY';
AuxvNames[44] = 'AT_L2_CACHESIZE';
AuxvNames[45] = 'AT_L2_CACHEGEOMETRY';
AuxvNames[46] = 'AT_L3_CACHESIZE';
AuxvNames[47] = 'AT_L3_CACHEGEOMETRY';
AuxvNames[51] = 'AT_MINSIGSTKSZ';

function hex(n) {
    return ('0000000' + ((n|0)+4294967296).toString(16)).substr(-8);
}

function hex64(n) {
    return (n.hi ? hex(n.hi) : '') + hex(n.lo);
}

function printString(view) {
    var offset = view.getUint32();

    var reset = view.tell();
    view.seek(offset);

    var size = view.getUint32()
    if ((size % 2) !== 0) {
        return '[invalid utf-16 string - odd byte length: ' + size + ']';
    }

    var data = [];
    var low = 0;
    for (var i = 0; i < size; ++i) {
        if ((i % 2) === 0) {
            low = view.getUint8();
        } else {
            data.push((view.getUint8() << 8) | low);
        }
    }

    view.seek(reset);

    return String.fromCharCode.apply(null, data);
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

oReq.onerror = function(oEvent) {
    loading.removeClass('active');
    loading.removeClass('progress-striped');
    progress.addClass('progress-bar-danger');
};

oReq.onprogress = function(oEvent) {
    if (oEvent.lengthComputable) {
        progress.css('width', ((oEvent.loaded / oEvent.total) * 100) + '%');
        loading.removeClass('progress-striped');
        loading.removeClass('active');
    } else {
        progress.css('width', '100%');
        loading.addClass('progress-striped');
        loading.addClass('active');
    }
};

oReq.onload = function(oEvent) {
    var arrayBuffer = oReq.response;
    if (oReq.status !== 200 || !arrayBuffer) {
        return oReq.onerror();
    }

    loading.remove();

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
        streamSize = view.getUint32();
        streamOffset = view.getUint32();

        if (StreamType[streamType]) {
            streamType = StreamType[streamType];
        }

        console.log(streamCount, i, streamType, streamSize);

        if (streamType === 'MD_UNUSED_STREAM') {
            continue;
        }

        var containerClasslist = 'well well-stream';
        html = '<dl class="dl-horizontal dl-minidump">';
        html += '<dt>Stream Type</dt><dd>' + streamType + '</dd>';
        //html += '<dt>Stream Size</dt><dd>0x' + hex(streamSize) + '</dd>';
        //html += '<dt>Stream Offset</dt><dd>0x' + hex(streamOffset) + '</dd>';
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
        case 'MD_MODULE_LIST_STREAM': {
            var moduleCount;
            html += '<dt>Module Count</dt><dd>' + (moduleCount = view.getUint32()) + '</dd>';
            html += '</dl><dl>';
            for (var j = 0; j < moduleCount; ++j) {
                html += '<dt>Module ' + j + '</dt><dd><div class="well well-sm"><dl class="dl-horizontal dl-minidump">';
                html += '<dt>Base</dt><dd>0x' + hex64(view.getUint64()) + '</dd>';
                html += '<dt>Size</dt><dd>0x' + hex(view.getUint32()) + '</dd>';
                html += '<dt>Checksum</dt><dd>0x' + hex(view.getUint32()) + '</dd>';
                html += '<dt>Time Stamp</dt><dd>' + new Date(view.getUint32() * 1000) + '</dd>';
                html += '<dt>Name</dt><dd>' + printString(view) + '</dd>';

                html += '<dt>Signature</dt><dd>0x' + hex(view.getUint32()) + '</dd>';
                html += '<dt>Struct Version</dt><dd>0x' + hex(view.getUint32()) + '</dd>';
                html += '<dt>File Version</dt><dd>' + view.getUint16() + '.' + view.getUint16() + '.' + view.getUint16() + '.' + view.getUint16() + '</dd>';
                html += '<dt>Product Version</dt><dd>' + view.getUint16() + '.' + view.getUint16() + '.' + view.getUint16() + '.' + view.getUint16() + '</dd>';
                html += '<dt>File Flags Mask</dt><dd>0x' + hex(view.getUint32()) + '</dd>';
                html += '<dt>File Flags</dt><dd>0x' + hex(view.getUint32()) + '</dd>';
                html += '<dt>File OS</dt><dd>0x' + hex(view.getUint32()) + '</dd>';
                html += '<dt>File Type</dt><dd>0x' + hex(view.getUint32()) + '</dd>';
                html += '<dt>File Subtype</dt><dd>0x' + hex(view.getUint32()) + '</dd>';
                html += '<dt>File Date</dt><dd>0x' + hex64(view.getUint64()) + '</dd>';

                var cvRecordSize = view.getUint32();
                var cvRecordOffset = view.getUint32();
                if (cvRecordSize > 0) {
                    var cvRecordReset = view.tell();
                    view.seek(cvRecordOffset);

                    var cvSignature = view.getUint32();
                    switch (cvSignature) {
                        case 0x4270454c: { // MD_CVINFOELF_SIGNATURE
                            var buildIdLength = cvRecordSize - 4;
                            var buildId = '';
                            if ((buildIdLength % 4) !== 0) throw new Error();
                            for (var k = 0; k < (buildIdLength / 4); ++k) {
                                buildId += hex(view.getUint32());
                            }

                            html += '<dt>Build ID</dt><dd>' + buildId + '</dd>';
                            break;
                        }
                        case 0x53445352: { // MD_CVINFOPDB70_SIGNATURE
                            var guid = '';
                            for (var k = 0; k < 4; ++k) {
                                guid += hex(view.getUint32());
                            }
                            html += '<dt>GUID</dt><dd>' + guid + '</dd>';
                            html += '<dt>Age</dt><dd>0x' + hex(view.getUint32()) + '</dd>';
                            var pdbName = '';
                            for (var k = 0; k < (cvRecordSize - 20); ++k) {
                                var c = view.getUint8();
                                if (c === 0) break;
                                pdbName += String.fromCharCode(c);
                            }
                            html += '<dt>PDB Name</dt><dd>' + pdbName + '</dd>';
                            break;
                        }
                        default:
                            html += '<dt>CodeView Type</dt><dd>0x' + hex(cvSignature) + '</dd>';
                    }

                    view.seek(cvRecordReset);
                }

                var miscRecordSize = view.getUint32();
                var miscRecordOffset = view.getUint32();
                if (miscRecordSize > 0) {
                    var miscRecordReset = view.tell();
                    view.seek(miscRecordOffset);

                    view.seek(miscRecordReset);
                }

                view.getUint64(); // Useless alignment bytes.
                view.getUint64(); // Useless alignment bytes.
                html += '</dl></div></dd>';
            }
            break;
        }
        case 'MD_EXCEPTION_STREAM': {
            html += '<dt>Exception Thread ID</dt><dd>0x' + hex(view.getUint32()) + '</dd>';
            view.getUint32() // Useless alignment bytes.
            html += '<dt>Exception Code</dt><dd>0x' + hex(view.getUint32()) + '</dd>';
            html += '<dt>Exception Flags</dt><dd>0x' + hex(view.getUint32()) + '</dd>';
            html += '<dt>Exception Record</dt><dd>0x' + hex64(view.getUint64()) + '</dd>';
            html += '<dt>Exception Address</dt><dd>0x' + hex64(view.getUint64()) + '</dd>';
            var exceptionParamCount = view.getUint32();
            view.getUint32() // Useless alignment bytes.
            for (var k = 0; k < exceptionParamCount; ++k) {
                html += '<dt>Param ' + k + '</dt><dd>0x' + hex64(view.getUint64()) + '</dd>';
            }
            break;
        }
        case 'MD_SYSTEM_INFO_STREAM': {
            html += '<dt>processor_architecture</dt><dd>0x' + hex(view.getUint16()) + '</dd>';
            html += '<dt>processor_level</dt><dd>0x' + hex(view.getUint16()) + '</dd>';
            html += '<dt>processor_revision</dt><dd>0x' + hex(view.getUint16()) + '</dd>';
            html += '<dt>number_of_processors</dt><dd>0x' + hex(view.getUint8()) + '</dd>';
            html += '<dt>product_type</dt><dd>0x' + hex(view.getUint8()) + '</dd>';
            html += '<dt>major_version</dt><dd>0x' + hex(view.getUint32()) + '</dd>';
            html += '<dt>minor_version</dt><dd>0x' + hex(view.getUint32()) + '</dd>';
            html += '<dt>build_number</dt><dd>0x' + hex(view.getUint32()) + '</dd>';
            html += '<dt>platform_id</dt><dd>0x' + hex(view.getUint32()) + '</dd>';
            html += '<dt>csd_version</dt><dd>' + printString(view) + '</dd>';
            html += '<dt>suite_mask</dt><dd>0x' + hex(view.getUint16()) + '</dd>';
            html += '<dt>reserved2</dt><dd>0x' + hex(view.getUint16()) + '</dd>';
            // CPU info stuff...
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
            html += '<pre class="well well-sm" style="max-height: 300px; overflow-y: auto;">' + view.getString(streamSize - 1).replace(/\0/g, ' ') + '</pre>';
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
        case 'MD_LINUX_AUXV': {
            var count = streamSize / 8;
            for (var j = 0; j < count; ++j) {
                var auxvId = view.getUint32();
                if (auxvId === 0) break;
                var auxvName = AuxvNames[auxvId] || hex(auxvId);
                html += '<dt>' + auxvName + '</dt><dd>' + hex(view.getUint32()) + '</dd>';
            }
            break;
        }
        default:
            containerClasslist += ' missing-stream';
            html += '<div class="alert alert-warning">This stream is currently unhandled.</div>';
        }

        html += '</dl>';
        view.seek(reset);

        html = '<div class="' + containerClasslist + '">' + html;
        html += '</div>';
        content.parent().append(html);
    }

    function toggleWell(e) {
        this.parentElement.classList.toggle('well-open');
    }

    var wells = document.getElementsByClassName('well-stream');
    for (var j = 0; j < wells.length; ++j) {
        wells[j].firstElementChild.onclick = toggleWell;
    }
};

$(function() {
    oReq.open('GET', 'download', true);
    oReq.responseType = 'arraybuffer';
    oReq.send(null);
});
