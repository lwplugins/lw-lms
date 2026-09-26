/**
 * Server-provided boot data (SettingsPage inline script `window.lwLmsAdmin`).
 */
const boot = window.lwLmsAdmin || {};

export const VERSION = boot.version || '';
export const NAMESPACE = boot.namespace || 'lw-lms/v1';
export const DOCS_URL =
	boot.docsUrl || 'https://github.com/lwplugins/lw-lms#readme';
// Enrollments and Quiz results need manage_lms (the REST routes check it too).
export const CAN_MANAGE_LEARNERS = !! boot.canManageLearners;
// The settings sections need manage_options (the REST route checks it too).
export const CAN_MANAGE_SETTINGS = !! boot.canManageSettings;
// Email addresses are shown and searchable only with list_users.
export const CAN_SEE_EMAILS = !! boot.canSeeEmails;
// Today in site time (Y-m-d): the earliest end date of a grant.
export const TODAY = boot.today || '';
export const LINKS = boot.links || {};
export const WOOCOMMERCE = !! boot.woocommerce;
