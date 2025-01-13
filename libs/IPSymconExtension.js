/*
 IPSymconExtension
 Version: 4.6
*/

class IPSymconExtension {
    constructor(zigbee, mqtt, state, publishEntityState, eventBus, settings, logger) {
        this.zigbee = zigbee;
        this.mqtt = mqtt;
        this.state = state;
        this.publishEntityState = publishEntityState;
        this.eventBus = eventBus;
        this.settings = settings;
        this.logger = logger;

        this.baseTopic = settings.get().mqtt.base_topic;
        this.symconTopic = 'symcon';

        this.eventBus.on('mqttMessage', this.onMQTTMessage.bind(this), this);
        logger.info('Loaded IP-Symcon Extension');
    }

    async start() {
        this.mqtt.subscribe(`${this.symconTopic}/${this.baseTopic}/#`);
    }

    async onMQTTMessage(data) {
        const topicPrefix = `${this.symconTopic}/${this.baseTopic}`;
        if (data.topic.startsWith(`${this.baseTopic}/SymconExtension/request/getDeviceInfo/`)) {
            try {
                const devicename = data.topic.split('/').slice(4).join('/');
                const message = JSON.parse(data.message);
                const device = this.zigbee.resolveEntity(devicename);
                let devicepayload = {};
                if (typeof device !== "undefined") {
                    devicepayload = this.#createDevicePayload(device, true);
                }
                devicepayload.transaction = message.transaction;
                this.logger.info('Symcon: request/getDevice');
                await this.mqtt.publish(`SymconExtension/response/getDeviceInfo/${devicename}`, JSON.stringify(devicepayload), {
                    retain: false,
                    qos: 0
                }, `${this.baseTopic}`, false, false);
            } catch (error) {
                let errormessage = 'Unknown Error'
                if (error instanceof Error) errormessage = error.message
                this.logger.error(`Symcon error (${errormessage}) at Topic ${data.topic}`);
            }
            return;
        }
        if (data.topic.startsWith(`${this.baseTopic}/SymconExtension/request/getGroupInfo`)) {
            try {
                const groupname = data.topic.split('/').slice(4).join('/');
                const message = JSON.parse(data.message);
                const groupExposes = this.#createGroupExposes(groupname);
                groupExposes.transaction = message.transaction;
                this.logger.info('Symcon: request/getGroupe');
                await this.mqtt.publish(`SymconExtension/response/getGroupInfo/${groupname}`, JSON.stringify(groupExposes), {
                    retain: false,
                    qos: 0
                }, `${this.baseTopic}`, false, false);
            } catch (error) {
                let errormessage = 'Unknown Error'
                if (error instanceof Error) errormessage = error.message
                this.logger.error(`Symcon error (${errormessage}) at Topic ${data.topic}`);
            }
            return;
        }
        if (data.topic == `${this.baseTopic}/SymconExtension/lists/request/getGroups`) {
            try {
                const message = JSON.parse(data.message);
                const groups = {
                    list: [],
                    transaction: 0,
                };
                groups.list = this.settings.getGroups();
                groups.transaction = message.transaction;
                this.logger.info('Symcon: lists/request/getGroups');
                await this.mqtt.publish('SymconExtension/lists/response/getGroups', JSON.stringify(groups), {
                    retain: false,
                    qos: 0
                }, `${this.baseTopic}`, false, false);
            } catch (error) {
                let errormessage = 'Unknown Error'
                if (error instanceof Error) errormessage = error.message
                this.logger.error(`Symcon error (${errormessage}) at Topic ${data.topic}`);
            }
            return;
        }
        if (data.topic == `${this.baseTopic}/SymconExtension/lists/request/getDevices`) {
            try {
                const message = JSON.parse(data.message);
                const devices = {
                    list: [],
                    transaction: 0,
                };
                try {
                    for (const device of this.zigbee.devicesIterator(this.#deviceNotCoordinator)) {
                        devices.list = devices.list.concat(this.#createDevicePayload(device, false));
                    }
                } catch (error) {
                    devices.list = this.zigbee.devices(false).map(device => this.#createDevicePayload(device, false));
                }
                devices.transaction = message.transaction;
                this.logger.info('Symcon: lists/request/getDevices');
                await this.mqtt.publish('SymconExtension/lists/response/getDevices', JSON.stringify(devices), {
                    retain: false,
                    qos: 0
                }, `${this.baseTopic}`, false, false);
            } catch (error) {
                let errormessage = 'Unknown Error'
                if (error instanceof Error) errormessage = error.message
                this.logger.error(`Symcon error (${errormessage}) at Topic ${data.topic}`);
            }
            return;
        }
        switch (data.topic) {
            case `${topicPrefix}/getDevices`: // deprecated
            case `${this.baseTopic}/bridge/symcon/getDevices`: // deprecated
                let devices = [];
                try {
                    for (const device of this.zigbee.devicesIterator(this.#deviceNotCoordinator)) {
                        devices = devices.concat(this.#createDevicePayload(device, false));
                    }
                } catch (error) {
                    devices = this.zigbee.devices(false).map(device => this.#createDevicePayload(device, false));
                }
                this.logger.info('Symcon: publish devices list');
                await this.#publishToMqtt('devices', devices);
                break;
            case `${topicPrefix}/getDevice`: // deprecated
            case `${this.baseTopic}/bridge/symcon/getDevice`: // deprecated
                if (data.message) {
                    const device = this.zigbee.resolveEntity(data.message);
                    if (typeof device !== "undefined") {
                        const devices = this.#createDevicePayload(device, true);
                        this.logger.info('Symcon: getDevice');
                        await this.#publishToMqtt(`${device.name}/deviceInfo`, devices);
                    }
                }
                break;
            case `${topicPrefix}/getGroups`: // deprecated
            case `${this.baseTopic}/bridge/symcon/getGroups`: // deprecated
                const groups = this.settings.getGroups();
                await this.#publishToMqtt('groups', groups);
                break;
            case `${topicPrefix}/getGroup`: // deprecated
            case `${this.baseTopic}/bridge/symcon/getGroup`: // deprecated
                if (data.message) {
                    const groupExposes = this.#createGroupExposes(data.message);
                    await this.#publishToMqtt(`${data.message}/groupInfo`, groupExposes);
                }
                break;
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
        };
    }

    async #publishToMqtt(topicSuffix, payload) {
        await this.mqtt.publish(`${topicSuffix}`, JSON.stringify(payload), {
            retain: false,
            qos: 0
        }, `${this.symconTopic}/${this.baseTopic}`, false, false);
    }

    #createGroupExposes(groupName) {
        const groupSupportedTypes = ['light', 'switch', 'lock', 'cover'];
        const groups = this.settings.getGroups();
        const groupExposes = {
            foundGroup: false
        };

        groupSupportedTypes.forEach(type => groupExposes[type] = {
            type,
            features: []
        });

        groups.forEach(group => {
            if (group.friendly_name === groupName) {
                groupExposes.foundGroup = true;
                this.#processGroupDevices(group, groupExposes);
            }
        });

        return groupExposes;
    }

    #processGroupDevices(group, groupExposes) {
        group.devices.forEach(deviceAddress => {
            const device = this.zigbee.resolveEntity(deviceAddress.substring(0, deviceAddress.indexOf('/')));
            if (typeof device !== "undefined") {
                this.#addDeviceExposesToGroup(device, groupExposes);
            }
        });
    }

    #addDeviceExposesToGroup(device, groupExposes) {
        let exposes = [];

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
    #deviceNotCoordinator(device) {
        return device.type !== 'Coordinator';
    }
}

module.exports = IPSymconExtension;