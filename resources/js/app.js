window.global = window.global ?? window;

import './echo';
import './brand-color';
import 'emoji-picker-element';
import { RingtonePlayer } from './ringtone';
import { conversationVideoCall } from './video-call';
import { conversationVideoCallControl } from './video-call-control';
import { groupConversationVideoCall } from './group-call';
import { sukiVendorMap } from './maps/vendor-map';

window.sukiRingtone = window.sukiRingtone ?? new RingtonePlayer();

window.conversationVideoCall = conversationVideoCall;
window.groupConversationVideoCall = groupConversationVideoCall;
window.conversationVideoCallControl = conversationVideoCallControl;
window.sukiVendorMap = sukiVendorMap;
