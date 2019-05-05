'use strict';

if (!String.prototype.padStart) {
	String.prototype.padStart = function padStart(targetLength,padString) {
		targetLength = targetLength>>0; //floor if number or convert non-number to 0;
		padString = String(padString || ' ');
		if (this.length > targetLength) {
			return String(this);
		}
		else {
			targetLength = targetLength-this.length;
			if (targetLength > padString.length) {
				padString += padString.repeat(targetLength/padString.length); //append to original to ensure we are longer than needed
			}
			return padString.slice(0,targetLength) + String(this);
		}
	};
}

if (!String.prototype.padEnd) {
	String.prototype.padEnd = function padEnd(targetLength,padString) {
		targetLength = targetLength>>0; //floor if number or convert non-number to 0;
		padString = String(padString || ' ');
		if (this.length > targetLength) {
			return String(this);
		}
		else {
			targetLength = targetLength-this.length;
			if (targetLength > padString.length) {
				padString += padString.repeat(targetLength/padString.length); //append to original to ensure we are longer than needed
			}
			return String(this) + padString.slice(0,targetLength);
		}
	};
}

const FRAME_TRUST = [
	'unknown',
	'stack scanning',
	'call frame info with scanning',
	'previous frame\'s frame pointer',
	'call frame info',
	'external stack walker',
	'instruction pointer in context',
];

function print_registers(indent, registers) {
	let register_count = 0;
	let line = indent;

	for (let register in registers) {
		line += register + ': 0x' + registers[register].toString(16).padStart(8, '0');

		register_count++;

		if (register_count < 3) {
			line += '  ';
		} else {
			console.log(line);

			register_count = 0;
			line = indent;
		}
	}

	if (register_count > 0) {
		console.log(line);
	}
}

function print_stack(indent, base, memory) {
	const kAddressBytes = 4;
	const kHeader = false;
	const kRowBytes = 16;
	const kChunkBytes = 8;
	const kChunkText = false;

	if (kHeader) {
		let line = indent + ' '.repeat(kAddressBytes * 2) + '  ';
		let string = '';
		for (let i = 0; i < kRowBytes; ++i) {
			line += i.toString(16).padStart(2, ' ') + ' ';
			string += ' ';
			//string += i.toString(16).padStart(2, ' ').substr(1, 1);

			if (kChunkBytes > 0 && i < (kRowBytes - 1) && (i % kChunkBytes) === (kChunkBytes - 1)) {
				line += ' ';
				if (kChunkText) {
					string += ' ';
				}
			}
		}
		line += '  ' + string + ' ';
		console.log(line);
	}

	for (let offset = 0; offset < memory.length;) {
		let line = indent + (base + offset).toString(16).padStart(kAddressBytes * 2, '0') + '  ';

		let string = '';
		for (let i = 0; i < kRowBytes; ++i, ++offset) {
			if (offset < memory.length) {
				line += memory[offset].toString(16).padStart(2, '0') + ' ';

				const character = String.fromCharCode(memory[offset]);
				string += (character.length === 1 && character.match(/^[ -~]$/)) ? character : '.';
			} else {
				line += '   ';
				string += ' ';
			}

			if (kChunkBytes > 0 && i < (kRowBytes - 1) && (i % kChunkBytes) === (kChunkBytes - 1)) {
				line += ' ';

				if (kChunkText) {
					string += ' ';
				}
			}
		}

		line += ' |' + string + '|';

		console.log(line);
	}
}

function print_instructions(indent, ip, instructions) {
	let bytes_per_line = 0;
	let crash_opcode = -1;

	for (let i = 0; i < instructions.length; ++i) {
		if (ip >= instructions[i].offset && (i === (instructions.length - 1) || ip < instructions[i + 1].offset)) {
			crash_opcode = i;
			break;
		}
	}

	if (crash_opcode >= 0) {
		for (let i = 0; i < instructions.length; ++i) {
			if (i < (crash_opcode - 5)) {
				continue;
			}

			if (i > (crash_opcode + 5)) {
				break;
			}

			const bytes = instructions[i].hex.length / 2;
			if (bytes > bytes_per_line) {
				bytes_per_line = bytes;
			}
		}
	}

	for (let i = 0; i < instructions.length; ++i) {
		if (crash_opcode >= 0) {
			if (i < (crash_opcode - 5)) {
				continue;
			}

			if (i > (crash_opcode + 5)) {
				break;
			}
		}

		let line = indent;

		if (crash_opcode >= 0 && i === crash_opcode) {
			line = '  >' + line.substr(3);
		}

		line += instructions[i].offset.toString(16).padStart(8, '0');
		line += '  ';
		line += instructions[i].hex.match(/.{2}/g).join(' ').padEnd((bytes_per_line * 3) - 1, ' ');
		line += '  ';
		line += instructions[i].mnemonic;

		console.log(line);
	}
}

function print_thread(i, crashed, thread) {
	let title = 'Thread ' + i;
	if (crashed) {
		title += ' (crashed)';
	}
	title += ':';

	console.log(title);

	let num_frames = thread.length;
	/*if (num_frames > 10) {
		num_frames = 10;
	}*/

	for (let i = 0; i < num_frames; ++i) {
		const frame = thread[i];

		const prefix = i.toString().padStart((num_frames - 1).toString().length, ' ') + ': ';
		const indent = '  ' + ' '.repeat(prefix.length);

		console.log('  ' + prefix + frame.rendered);

		if (frame.url) {
			console.log(indent + frame.url);
		}

		if (frame.registers) {
			//console.log(indent + 'Registers');
			print_registers(indent, frame.registers);
			console.log('');
		}

		if (frame.instructions) {
			//console.log(indent + 'Disassembly');
			print_instructions(indent, frame.instruction, frame.instructions);
			console.log('');
		}

		if (frame.stack) {
			//console.log(indent + 'Stack Memory');
			print_stack(indent, frame.registers && frame.registers.esp, base64ToUint8Array(frame.stack));
			console.log('');
		}

		console.log(indent + 'Found via ' + (FRAME_TRUST[frame.trust] || FRAME_TRUST[0]));
		console.log('');

		console.log('');
	}
}

function base64ToUint8Array(base64) {
	var binary_string =  window.atob(base64);
	var len = binary_string.length;
	var bytes = new Uint8Array( len );
	for (var i = 0; i < len; i++)		{
		bytes[i] = binary_string.charCodeAt(i);
	}
	return bytes;
}

function analyze(data) {
	if (data.crashed) {
		console.log(data.crash_reason + ' accessing 0x' + data.crash_address.toString(16));
		console.log('');
	}

	if (typeof data.requesting_thread !== 'undefined' && data.requesting_thread >= 0) {
		const thread = data.threads[data.requesting_thread];
		print_thread(data.requesting_thread, true, thread);
	}

	for (let i = 0; i < data.threads.length; ++i) {
		if (i === data.requesting_thread) {
			continue;
		}

		const thread = data.threads[i];
		print_thread(i, false, thread);
	}
}
