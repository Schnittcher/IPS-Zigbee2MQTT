class IPSymconExtension {

    constructor(zigbee, mqtt, state, publishEntityState, eventBus, settings, logger) {
        this.zigbee = zigbee;
        this.mqtt = mqtt;
        this.state = state;
        this.publishEntityState = publishEntityState;
        this.eventBus = eventBus;
        this.settings = settings;
        this.logger = logger;

        this.zigbeeHerdsmanConverters = require('zigbee-herdsman-converters');        
        
        this.baseTopic = `${settings.get().mqtt.base_topic}`;
        this.symconTopic = 'symcon';
        
        this.eventBus.on('mqttMessage', this.onMQTTMessage.bind(this), this);
        logger.info('Loaded IP-Symcon Extension');

    }


    async start() {
        this.mqtt.subscribe(`${this.symconTopic}/${this.baseTopic}/#`);
    }

    async onMQTTMessage(data) {
        const topicBase = `${this.symconTopic}/${this.baseTopic}`;
        switch (data.topic) {
            case `${topicBase}/getDevices`:
                await this.handleGetDevices();
                break;
            case `${topicBase}/getDevice`:
                await this.handleGetDevice(data.message);
                break;
            case `${topicBase}/getGroups`:
                await this.handleGetGroups();
                break;
            case `${topicBase}/getGroup`:
                await this.handleGetGroup(data.message);
                break;
            default:
                this.logger.info('Unrecognized topic:', data.topic);
        }
    }

    async handleGetDevices() {
        const devices = this.zigbee.devices(false).map(device => this.formatDevicePayload(device));
        this.logger.info('Symcon: publish devices list');
        await this.mqtt.publish('devices', JSON.stringify(devices), {retain: false, qos: 0}, `${this.symconTopic}/${this.baseTopic}`, false, false);
    }

    formatDevicePayload(device) {
        const definition = this.zigbeeHerdsmanConverters.findByDevice(device.zh) || {};
        return {
            ieeeAddr: device.ieeeAddr,
            type: device.zh.type,
            networkAddress: device.zh.networkAddress,
            model: device.definition.model,
            vendor: device.definition.vendor,
            description: device.definition.description,
            friendly_name: device.name,
            manufacturerName: device.zh.manufacturerName,
            powerSource: device.zh.powerSource,
            modelID: device.zh.modelID,
        };
    }

    async handleGetDevice(deviceName) {
        const device = this.zigbee.resolveEntity(deviceName);
        if (device) {
            await this.mqtt.publish(`${device.name}/deviceInfo`, JSON.stringify(device), {retain: false, qos: 0}, `${this.symconTopic}/${this.baseTopic}`, false, false);
        }
    }

    async handleGetGroups() {
        const groups = this.settings.getGroups();
        await this.mqtt.publish('groups', JSON.stringify(groups), {retain: false, qos: 0}, `${this.symconTopic}/${this.baseTopic}`, false, false);
    }

    async handleGetGroup(groupName) {
        const groups = this.settings.getGroups();
        const groupExposes = this.generateGroupExposes(groups, groupName);
        this.logger.debug('Symcon Extension :: groupExposes', JSON.stringify(groupExposes));
        await this.mqtt.publish(`${groupName}/groupInfo`, JSON.stringify(groupExposes), {retain: false, qos: 0}, `${this.symconTopic}/${this.baseTopic}`, false, false);
        this.logger.info('Symcon Extension :: groupExposes published');
    }

    generateGroupExposes(groups, groupName) {
        const groupExposes = { light: [], switch: [], lock: [], cover: [] };
        // ... (Ihre Logik zur Erstellung von groupExposes)
        return groupExposes;
    }

    async stop() {
        this.eventBus.removeListeners(this);
    }
}

module.exports = IPSymconExtension;
