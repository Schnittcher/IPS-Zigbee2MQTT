/*
 IPSymconExtension
 Version: 5.40
*/

class MyLogger {
    constructor(logger) {
        // Das von Zigbee2MQTT übergebene logger-Objekt (ggf. leer)
        this.logger = logger || {};
    }

    info(...args) {
        if (typeof this.logger.info === 'function') {
            // Logger von Z2M nutzen
            this.logger.info(...args);
        } else {
            // Fallback: auf console.log
            console.log('[MyExtension][info]', ...args);
        }
    }

    error(...args) {
        if (typeof this.logger.error === 'function') {
            this.logger.error(...args);
        } else {
            console.error('[MyExtension][error]', ...args);
        }
    }

    debug(...args) {
        if (typeof this.logger.debug === 'function') {
            this.logger.debug(...args);
        } else {
            console.log('[MyExtension][debug]', ...args);
        }
    }
}

class IPSymconExtension {
    constructor(zigbee, mqtt, state, publishEntityState, eventBus, enableDisableExtension, restartCallback, addExtension, settings, baseLogger) {
        this.zigbee = zigbee;
        this.mqtt = mqtt;
        this.state = state;
        this.publishEntityState = publishEntityState;
        this.eventBus = eventBus;
        this.settings = settings;
        this.logger = new MyLogger(baseLogger);
        this.baseTopic = this.settings.get().mqtt.base_topic;
        this.symconExtensionTopic = 'SymconExtension';
        this.eventBus.onMQTTMessage(this, this.onMQTTMessage.bind(this));
        this.logger.info('Loaded IP-Symcon Extension');
    }

    async start() {
        this.mqtt.subscribe(`${this.baseTopic}/${this.symconExtensionTopic}/#`);

    }

    async onMQTTMessage(data) {
        if (!data.topic.startsWith(`${this.baseTopic}/${this.symconExtensionTopic}`)) {
            return;
        }
        let message = {};
        const transaction = JSON.parse(data.message).transaction;
        const topic = (data.topic.slice(this.baseTopic.length + 1)).replace('request', 'response');
        try {
            if (data.topic.startsWith(`${this.baseTopic}/${this.symconExtensionTopic}/request/getDeviceInfo/`)) {
                this.logger.info('Symcon: request/getDeviceInfo');
                const devicename = data.topic.split('/').slice(4).join('/');
                const device = this.zigbee.resolveEntity(devicename);
                if (typeof device !== "undefined") {
                    message = this.#createDevicePayload(device, true);
                }
            }
            if (data.topic.startsWith(`${this.baseTopic}/${this.symconExtensionTopic}/request/getGroupInfo`)) {
                this.logger.info('Symcon: request/getGroupInfo');
                const groupname = data.topic.split('/').slice(4).join('/');
                message = this.#createGroupExposes(groupname);
            }
            if (data.topic == `${this.baseTopic}/${this.symconExtensionTopic}/lists/request/getGroups`) {
                this.logger.info('Symcon: lists/request/getGroups');
                message.list = [];
                for (const group of this.zigbee.groupsIterator()) {
                    const listEntry = {
                        devices: [],
                        friendly_name: group.options.friendly_name,
                        ID: group.zh.groupID
                    }
                    for (const device of group.zh.members) {
                        listEntry.devices.push(device.deviceIeeeAddress);
                    }
                    if (typeof listEntry.friendly_name !== "undefined") {
                        message.list.push(listEntry);
                    }
                }
            }
            if (data.topic == `${this.baseTopic}/${this.symconExtensionTopic}/lists/request/getDevices`) {
                this.logger.info('Symcon: lists/request/getDevices');
                message.list = [];
                for (const device of this.zigbee.devicesIterator()) {
                    message.list = message.list.concat(this.#createDevicePayload(device, false));
                }
            }
            message.transaction = transaction;
            await this.mqtt.publish(topic, JSON.stringify(message));
            return;
        } catch (error) {
            message.transaction = transaction;
            await this.mqtt.publish(topic, JSON.stringify(message));
            let errormessage = 'Unknown Error'
            if (error instanceof Error) errormessage = error.message
            this.logger.error(`Symcon error (${errormessage}) at Topic ${data.topic}`);
        }

    }

    async stop() {
        this.eventBus.removeListeners(this);
    }

    #createDevicePayload(device, boolExposes) {
        let exposes;
        if (boolExposes) {
            exposes = device.exposes();
        }
        return {
            ieeeAddr: device.ieeeAddr,
            type: device.zh.type,
            networkAddress: device.zh.networkAddress,
            model: device.definition?.model ?? 'Unknown Model',
            vendor: device.definition?.vendor ?? 'Unknown Vendor',
            description: device.definition?.description ?? 'No description',
            friendly_name: device.name,
            manufacturerName: device.zh.manufacturerName,
            powerSource: device.zh.powerSource,
            modelID: device.zh.modelID,
            exposes: exposes,
            filtered_attributes: device.options?.filtered_attributes ?? [],
        };
    }

    #createGroupExposes(groupname) {
        const groupSupportedTypes = ['light', 'switch', 'lock', 'cover'];
        const groupExposes = {
            foundGroup: false
        };
        groupSupportedTypes.forEach(type => groupExposes[type] = {
            type,
            features: []
        });

        const group = this.zigbee.resolveEntity(groupname);
        if (typeof group !== "undefined") {
            groupExposes.foundGroup = true;
            this.#processGroupDevices(group, groupExposes);
        }
        return groupExposes;
    }

    #processGroupDevices(group, groupExposes) {
        group.zh.members.forEach(member => {
            this.logger.info(`Symcon processGroupDevices: ${JSON.stringify(member.deviceIeeeAddress)}`);
            const device = this.zigbee.resolveEntity(member.deviceIeeeAddress);
            this.#addDeviceExposesToGroup(device, groupExposes);
        });
    }

    #addDeviceExposesToGroup(device, groupExposes) {
        let exposes = [];
        this.logger.info(`Symcon addDeviceExposesToGroup: ${JSON.stringify(device)}`);
        // Überprüfen, ob 'definition' vorhanden ist und Exposes hinzufügen
        if (device.definition && device.definition.exposes) {
            exposes = exposes.concat(device.definition.exposes);
        }

        // Überprüfen, ob '_definition' vorhanden ist und Exposes hinzufügen
        if (device._definition && device._definition.exposes) {
            exposes = exposes.concat(device._definition.exposes);
        }

        // Verarbeite alle gesammelten Exposes
        exposes.forEach(expose => {
            const type = expose.type;
            if (groupExposes[type]) {
                this.#processExposeFeatures(expose, groupExposes[type]);
            }
        });
    }
    #processExposeFeatures(expose, groupExposeType) {
        expose.features.forEach(feature => {
            if (!groupExposeType.features.some(f => f.property === feature.property)) {
                groupExposeType.features.push(feature);
            }
        });
    }
}

module.exports = IPSymconExtension;