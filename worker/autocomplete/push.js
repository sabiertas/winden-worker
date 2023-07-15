const axios = require('axios').default;
const core = require('@actions/core');
const fs = require('fs');

(async () => {
    const worker_token = process.env['worker_token'];
    const route_push = process.env['route_push'];
    const uuid = process.env['uuid'];
    const run_id = process.env['run_id'];
    const entity = process.env['entity'];
    let run_status = process.env['run_status'];

    let payload = false;

    try {
        payload = fs.readFileSync('./autocomplete.json', 'utf8');
        payload = Buffer.from(payload).toString('base64');
    } catch (err) {
        run_status = 'failure';
    }

    axios
        .post(route_push, {
            entity: entity,
            uuid: uuid,
            run_id: run_id,
            run_status: run_status,
            payload: payload,
        }, {
            headers: {
                'Worker-Token': worker_token
            }
        })
        .catch(error => {
            core.setFailed(error.message);
        });
})();