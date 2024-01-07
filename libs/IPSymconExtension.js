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

    /**
     * This method is called by the controller once Zigbee2MQTT has been started.
     */
    async start() {
        this.mqtt.subscribe(`${this.symconTopic}/${this.baseTopic}/#`);
    }
    
async onMQTTMessage(data) {
    switch (data.topic) {
        case this.symconTopic + '/' + this.baseTopic + '/getDevices':
            // let coordinator = await this.zigbee.getCoordinatorVersion();
            let devices = this.zigbee.devices(false).map((device) => {
                const payload = {
                    ieeeAddr: device.ieeeAddr,
                    type: device.zh.type,
                    networkAddress: device.zh.networkAddress,
                };

                let definition = this.zigbeeHerdsmanConverters.findByDevice(device.zh);
                payload.model = definition ? definition.model : device.zh.modelID;
		payload.vendor = device.definition && device.definition.vendor ? device.definition.vendor : 'Unknown Vendor';
                payload.description = device.definition && device.definition.description ? device.definition.description : 'No description';
                //payload.exposes =  definition ? definition.exposes : '-';
                payload.friendly_name = device.name;
                //payload.manufacturerID = device.zh.manufacturerID;
                payload.manufacturerName = device.zh.manufacturerName;
                payload.powerSource = device.zh.powerSource;
                payload.modelID = device.zh.modelID;
                //payload.hardwareVersion = device.zh.hardwareVersion;
                //payload.softwareBuildID = device.zh.softwareBuildID;
                //payload.dateCode = device.zh.dateCode;
                //payload.lastSeen = device.zh.lastSeen;

                return payload;
            });
            this.logger.info('Symcon: publish devices list');
            await this.mqtt.publish('devices', JSON.stringify(devices), {retain: false, qos: 0}, this.symconTopic + '/' + this.baseTopic, false, false);
            break;
        case this.symconTopic + '/' + this.baseTopic + '/getDevice':
            let device = this.zigbee.resolveEntity(data.message);
			if (data.message) {
				//this.logger.info('Symcon: publish device information for' + data.message);
				//let device = this.zigbee.deviceByNetworkAddress(parseInt(data.message))
				await this.mqtt.publish(device.name + '/deviceInfo', JSON.stringify(device), {retain: false, qos: 0}, this.symconTopic + '/' + this.baseTopic, false, false);
			}
            break;
        case (this.symconTopic + '/' + this.baseTopic + '/getGroups'):
            var groups = this.settings.getGroups();
            await this.mqtt.publish('groups', JSON.stringify(groups), {retain: false, qos: 0}, this.symconTopic + '/' + this.baseTopic, false, false);
            break;
        case (this.symconTopic + '/' + this.baseTopic + '/getGroup'):
            const groupSupportedTypes = ['light', 'switch', 'lock', 'cover'];
            var groups = this.settings.getGroups();
            const groupExposes = {};
            let groupName = data.message;
            
            groupSupportedTypes.forEach(element => {
                groupExposes[element] = {
                    type: element,
                    features: []
                }
            });

            groups.forEach(function(group) {
                if (group.friendly_name === groupName) {
                    this.logger.debug(JSON.stringify(group));

                    const groupDevices = group.devices;
                    
                    groupDevices.forEach(function(device) {
                       const deviceAddress = device.substring(0, device.indexOf('/')); 
                       const tmpDevice = this.zigbee.resolveEntity(deviceAddress);
                       const exposes = tmpDevice._definition.exposes;

                        exposes.forEach(function(expose) {    
                            switch (expose.type) {
                                case 'light':
                                    // groupExposes.light.type = 'light';
                                    expose.features.forEach(function(feature) {    
                                        switch (feature.property) {
                                            case 'state':
                                                this.logger.debug('Symcon Extension :: Group Light State Exposes');
                                                var index = groupExposes.light.features.findIndex(f => f.property == 'state');
                                                if (index == -1) {
                                                    groupExposes.light.features.push(feature);   
                                                }
                                                        
                                                break;
                                            case 'brightness':
                                                this.logger.debug('Symcon Extension :: Group Light Brightness Exposes');
                                                var index = groupExposes.light.features.findIndex(f => f.property == 'brightness');
                                                
                                                if (index == -1) {
                                                    groupExposes.light.features.push(feature);
                                                }
                                                if (index >= 0) {
                                                    if (feature.value_max > groupExposes.light.features[index].value_max) {
                                                        this.logger.debug('set new max brightness');
                                                        groupExposes.light.features[index].value_max = feature.value_max;
                                                    }
                                                    if (feature.value_min > groupExposes.light.features[index].value_min) {
                                                        this.logger.debug('set new min brightness');
                                                        groupExposes.light.features[index].value_min = feature.value_min;
                                                    }
                                                }
                                                break;
                                            case 'color_temp':
                                                this.logger.debug('Symcon Extension :: Group Light Color_Temp Exposes');
                                                
                                                var index = groupExposes.light.features.findIndex(f => f.property == 'color_temp');
                                                
                                                if (index == -1) {
                                                    groupExposes.light.features.push(feature);
                                                }
                                                if (index >= 0) {
                                                    if (feature.value_max > groupExposes.light.features[index].value_max) {
                                                        this.logger.debug('set new max brightness');
                                                        groupExposes.light.features[index].value_max = feature.value_max;
                                                    }
                                                    if (feature.value_min > groupExposes.light.features[index].value_min) {
                                                        this.logger.debug('set new min brightness');
                                                        groupExposes.light.features[index].value_min = feature.value_min;
                                                    }
                                                }
                                                break
                                            case 'color':
                                                this.logger.debug('Symcon Extension :: Group Light Color Exposes');
                                                var index = groupExposes.light.features.findIndex(f => f.name == 'color_xy');
                                                if (index == -1) {
                                                    groupExposes.light.features.push(feature);
                                                }
                                                break;
                                            default:
                                                break;
                                        }
                                    }.bind(this));
                                case 'switch':
                                    expose.features.forEach(function(feature, index) {    
                                        switch (feature.property) {
                                            case 'state':
                                                this.logger.debug('Symcon Extension :: Group Switch State Exposes');
                                                var index = groupExposes.switch.features.findIndex(f => f.property == 'state');
                                                if (index == -1) {
                                                    groupExposes.switch.features.push(feature);   
                                                }
                                                break;
                                        }
                                    }.bind(this));
                                    break;
                                case 'lock':
                                    break;
                                case 'cover':
                                    break;
                                case 'default':
                                    break;
                            }

                        }.bind(this));
                       
                    }.bind(this));
                }
            }.bind(this));
            this.logger.debug('Symcon Extension :: groupExposes');          
            this.logger.debug(JSON.stringify(groupExposes));
            this.mqtt.publish(groupName + '/groupInfo', JSON.stringify(groupExposes), {retain: false, qos: 0}, this.symconTopic + '/' + this.baseTopic, false, false);
            this.logger.info('Symcon Extension :: groupExposes published');
            break;
        default:
         console.log('default');
        }
    }
    /**
     * Is called once the extension has to stop
     */
    async stop() {
        this.eventBus.removeListeners(this);
    }
}

module.exports = IPSymconExtension;
