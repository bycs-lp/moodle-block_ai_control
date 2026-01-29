// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Main module for the block_ai_control.
 *
 * @module     block_ai_control/ai_control
 * @copyright  2024 ISB Bayern
 * @author     Philipp Memmel
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {getAiconfig} from 'block_ai_control/repository';
import {init as initControlArea} from 'block_ai_control/ai_control_config';
import {init as initInfoArea} from 'block_ai_control/ai_control_info';


let baseElement = null;

/**
 * Main handler for the block_ai_control.
 *
 * @param {string} selector the selector of the rendered block main element.
 */
export const init = async(selector) => {
    baseElement = document.querySelector(selector);
    if (!baseElement) {
        return;
    }
    const contextid = baseElement.dataset.contextid;
    const infoArea = baseElement.querySelector('[data-aicontrol="info"]');
    const controlArea = baseElement.querySelector('[data-aicontrol="config"]');

    const aiconfig = await getAiconfig(contextid);

    if (controlArea) {
        await initControlArea(controlArea, aiconfig);
    }

    // It's important to render the info area AFTER the control area, because the info area needs to add
    // a change listener to the config area.
    await initInfoArea(infoArea, aiconfig);
};

export const convertTargetUnixTimeToCountdown = (unixTime) => {
    const distance = new Date(unixTime * 1000) - Date.now();
    return {
        days: Math.floor(distance / (1000 * 60 * 60 * 24)),
        hours: Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60)),
        minutes: Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60)),
        seconds: Math.floor((distance % (1000 * 60)) / 1000),
    };
};
