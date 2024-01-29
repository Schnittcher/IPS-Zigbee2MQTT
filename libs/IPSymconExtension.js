const ZigbeeHerdsmanConverters = require('zigbee-herdsman-converters');

class IPSymconExtension {
    constructor(zigbee, mqtt, state, publishEntityState, eventBus, settings, logger) {
        this.zigbee = zigbee;
        this.mqtt = mqtt;
        this.state = state;
        this.publishEntityState = publishEntityState;
        this.eventBus = eventBus;
        this.settings = settings;
        this.logger = logger;
        this.zigbeeHerdsmanConverters = ZigbeeHerdsmanConverters;

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
        switch (data.topic) {
            case `${topicPrefix}/getDevices`:
                const devices = this.zigbee.devices(false).map(device => this.#createDevicePayload(device, false));
                this.logger.info('Symcon: publish devices list');
                await this.#publishToMqtt('devices', devices);
                break;
            case `${topicPrefix}/getDevice`:
                if (data.message) {
                    const device = this.zigbee.resolveEntity(data.message);
                    const devices = this.#createDevicePayload(device, true);
                    this.logger.info('Symcon: getDevice');
                    await this.#publishToMqtt(`${device.name}/deviceInfo`, devices);
                }
                break;
            case `${topicPrefix}/getGroups`:
                const groups = this.settings.getGroups();
                await this.#publishToMqtt('groups', groups);
                break;
            case `${topicPrefix}/getGroup`:
                if (data.message) {
                    const groupExposes = this.#createGroupExposes(data.message);
                    await this.#publishToMqtt(`${data.message}/groupInfo`, groupExposes);
                }
                break;
            default:
                console.log('Unhandled MQTT topic');
        }
    }

    async stop() {
        this.eventBus.removeListeners(this);
    }

    #createDevicePayload(device, boolExposes) {
        const definition = this.zigbeeHerdsmanConverters.findByDevice(device.zh);
        let exposes;

            // Überprüfen, ob 'exposes' als Funktion definiert ist und sie ausführen
    //        if (typeof definition.exposes === 'function') {
    //            exposes = definition.exposes(device, this.settings).map(e => e.toJSON());
    //        }
            // Sonst, übernehmen der 'exposes', falls sie direkt als Array definiert sind
    //        else if (definition.exposes) {
    //            exposes = definition.exposes;
    //        }
            // Fallback, falls keine 'exposes' definiert sind
    //        else {
    //            exposes = 'No Exposes';
    //        }
            
            if (boolExposes) {
                exposes =  device.exposes();
            }

        return {
            ieeeAddr: device.ieeeAddr,
            type: device.zh.type,
            networkAddress: device.zh.networkAddress,
            model: definition?.model ?? 'Unknown Model',
            vendor: definition?.vendor ?? 'Unknown Vendor',
            description: definition?.description ?? 'No description',
            friendly_name: device.name,
            manufacturerName: device.zh.manufacturerName,
            powerSource: device.zh.powerSource,
            modelID: device.zh.modelID,
            exposes: exposes,
        };
    }

    async #publishToMqtt(topicSuffix, payload) {
        await this.mqtt.publish(`${topicSuffix}`, JSON.stringify(payload), { retain: false, qos: 0 }, `${this.symconTopic}/${this.baseTopic}`, false, false);
    }

    #createGroupExposes(groupName) {
        const groupSupportedTypes = ['light', 'switch', 'lock', 'cover'];
        const groups = this.settings.getGroups();
        const groupExposes = {};

        groupSupportedTypes.forEach(type => groupExposes[type] = { type, features: [] });

        groups.forEach(group => {
            if (group.friendly_name === groupName) {
                this.#processGroupDevices(group, groupExposes);
            }
        });

        return groupExposes;
    }

    #processGroupDevices(group, groupExposes) {
        group.devices.forEach(deviceAddress => {
            const device = this.zigbee.resolveEntity(deviceAddress.substring(0, deviceAddress.indexOf('/')));
            this.#addDeviceExposesToGroup(device, groupExposes);
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
}

module.exports = IPSymconExtension;
