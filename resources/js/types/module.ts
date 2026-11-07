/**
 * The delivery modules a client can broadcast over. The platform team can switch
 * a module off for every client; a tenant can also switch one off for itself.
 */
export type ModuleKey = 'sms' | 'voice' | 'email';

/** Whether each delivery module is live for the signed-in account. */
export type ModuleAvailability = Record<ModuleKey, boolean>;
