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
 * Handler for the config area of block_ai_control.
 *
 * @module     block_ai_control/ai_control_config
 * @copyright  2024 ISB Bayern
 * @author     Philipp Memmel
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {updateAiconfig} from 'block_ai_control/repository';
import {convertTargetUnixTimeToCountdown} from 'block_ai_control/ai_control';
import Templates from 'core/templates';
import {getString} from 'core/str';
import {alert as moodleAlert} from 'core/notification';
import ModalSaveCancel from 'core/modal_save_cancel';
import ModalEvents from 'core/modal_events';

let baseElement = null;
let aiconfig = null;

/**
 * @type {number} The target time as unix timestamp (seconds since 1/1/1970).
 *
 * Will constantly be updated by the UX and finally be used to submit the updated data to the
 * update endpoint.
 */
let currentTargetTime = 0;


/**
 * Init the handler for the control area of block_ai_control.
 *
 * @param {HTMLElement} element the HTML element of the control area
 * @param {object} aiconfigObject the aiconfig which has been fetched from the external function
 */
export const init = async(element, aiconfigObject) => {
    aiconfig = aiconfigObject;
    baseElement = element;
    const contextid = baseElement.parentElement.dataset.contextid;

    const templateContext = {};
    templateContext.identifier = 'aiconfig_enabled';
    templateContext.text = await getString('toggleai', 'block_ai_control');
    templateContext.checked = aiconfig.enabled;
    templateContext.coursecontextid = contextid;
    templateContext.showviewprompts = aiconfig.showviewprompts;

    currentTargetTime = aiconfig.expiresat;

    templateContext.expiresat = convertUnixtimeToDateElementFormat(currentTargetTime);

    templateContext.purposes = [];
    aiconfig.purposes.forEach(purpose => {
        const purposeConfig = {
            checked: purpose.allowed,
            identifier: 'purpose_' + purpose.name,
            text: purpose.displayname,
            purposename: purpose.name
        };
        templateContext.purposes.push(purposeConfig);
    });

    const {html, js} = await Templates.renderForPromise('block_ai_control/ai_control_config', {...templateContext});
    Templates.replaceNodeContents(baseElement, html, js);

    baseElement.querySelectorAll('[data-aicontrol-item^="switchexpiryview"]').forEach(button => {
        button.addEventListener('click', () => {
            updateTargetTime();
            const elementsToToggleVisibility = baseElement.querySelectorAll('[data-aicontrol-show]');
            elementsToToggleVisibility.forEach(element => {
                element.dataset.aiconfigShow = element.dataset.aiconfigShow === '1' ? '0' : '1';
                element.classList.toggle('d-none');
            });
        });
    });

    baseElement.querySelector('[data-aicontrol="submitbutton"]').addEventListener('click', async() => {
        await handleSubmitButtonClick();
    });
    baseElement.querySelector('[data-toggle-identifier="aiconfig_enabled"] input').addEventListener('change', () => {
        baseElement.querySelector('[data-aicontrol="purposeslist"]').classList.toggle('d-none');
        baseElement.querySelector('[data-aicontrol="expirydate"]').classList.toggle('d-none');
    });
};

const handleSubmitButtonClick = async() => {
    updateTargetTime();
    const currentTime = new Date();
    if (Math.floor(currentTime.getTime() / 1000) > currentTargetTime) {
        await moodleAlert(getString('error', 'core'), getString('error_targettimeinpast', 'block_ai_control'));
        return;
    }

    // We check if we have to show the modal here: We only show the modal if the current enabled state is false and at the same time
    // are switching from false to true.
    const isCurrentlyDisabled = aiconfig.enabled === false
        && baseElement.parentElement.querySelector('[data-toggle-identifier="aiconfig_enabled"]').dataset.checked === '1';

    if (aiconfig.infoText.length > 0 && isCurrentlyDisabled) {
        const infoModal = await ModalSaveCancel.create({
            title: getString('infotextmodalheading', 'block_ai_control'),
            body: aiconfig.infoText,
            show: true,
            buttons: {
                'save': getString('confirm', 'moodle'),
                'cancel': getString('cancel', 'moodle'),
            },
        });
        infoModal.getRoot().on(ModalEvents.save, async() => {
            const refreshedData = await updateAiconfig(buildUpdateAiconfigObject());
            aiconfig = refreshedData;
            dispatchChangedEvent(refreshedData);
        });
    } else {
        const refreshedData = await updateAiconfig(buildUpdateAiconfigObject());
        aiconfig = refreshedData;
        dispatchChangedEvent(refreshedData);
    }
};

/**
 * Collects information from the DOM and builds the new aiconfig object.
 *
 * This object then can be sent to the update external function.
 *
 * @returns {object} the aiconfig object which then can be used to send it to the update webservice.
 */
const buildUpdateAiconfigObject = () => {
    const purposeConfigElements = baseElement.querySelectorAll('[data-toggle-identifier^="purpose_"]');
    const enabledToggle = baseElement.querySelector('[data-toggle-identifier="aiconfig_enabled"]');

    const aiconfig = {};
    aiconfig.id = baseElement.parentElement.dataset.contextid;
    aiconfig.enabled = !!parseInt(enabledToggle.dataset.checked);
    aiconfig.expiresat = currentTargetTime;
    aiconfig.purposes = [];
    purposeConfigElements.forEach(purposeConfigElement => {
        const purpose = {};
        purpose.id = purposeConfigElement.dataset.toggleIdentifier.replace(/^purpose_/, '');
        purpose.allowed = !!parseInt(purposeConfigElement.dataset.checked);
        aiconfig.purposes.push(purpose);
    });
    return aiconfig;
};

/**
 * Helper function to dispatch a changed event.
 *
 * The info area will react to this changed event so it knows when it has to rerender itself.
 *
 * @param {object} refreshedAiconfig the refreshed aiconfig object which has been received by the update external function.
 */
const dispatchChangedEvent = (refreshedAiconfig) => {
    baseElement.dispatchEvent(new CustomEvent('aiconfigUpdated', {
        detail: {
            aiconfig: refreshedAiconfig
        }
    }));
};

const updateTargetTime = () => {
    const durationElement = baseElement.querySelector('[data-aicontrol-item="expiryduration"]');
    const expirydurationDays = baseElement.querySelector('[data-aicontrol-item="expiryduration_days"]');
    const expirydurationHours = baseElement.querySelector('[data-aicontrol-item="expiryduration_hours"]');
    const expirydurationMinutes = baseElement.querySelector('[data-aicontrol-item="expiryduration_minutes"]');

    const dateElement = baseElement.querySelector('[data-aicontrol-item="expirydate"]');

    if (durationElement.dataset.aiconfigShow === '1') {
        const currentTime = new Date();
        currentTargetTime = currentTime.getTime()
            + (expirydurationDays.value * 24 * 60 * 60 * 1000)
            + (expirydurationHours.value * 60 * 60 * 1000)
            + (expirydurationMinutes.value * 60 * 1000);
        currentTargetTime = Math.round(currentTargetTime / 1000);
    } else {
        currentTargetTime = Math.round(parseInt(+new Date(dateElement.value)) / 1000);
    }

    // Now our global time is set correctly, we can update both UI elements.
    dateElement.value = convertUnixtimeToDateElementFormat(currentTargetTime);

    const {days, hours, minutes} = convertTargetUnixTimeToCountdown(currentTargetTime);
    expirydurationDays.value = days;
    expirydurationHours.value = hours;
    expirydurationMinutes.value = minutes;
};

/**
 * Converts a unix time stamp (seconds since 1/1/1970) into the format a <input type="datetime-local"> element expects.
 *
 * It will convert it into the local time of the user's browser.
 *
 * @param {number} unixtime the unix time stamp in seconds sind 1/1/1970
 * @returns {string} the string to be set as value of the input datetime-local element
 */
const convertUnixtimeToDateElementFormat = (unixtime) => {
    const localTargetTime = new Date(unixtime * 1000);
    localTargetTime.setTime(localTargetTime.getTime() - localTargetTime.getTimezoneOffset() * 60 * 1000);
    return localTargetTime.toISOString().slice(0, 16);
};
