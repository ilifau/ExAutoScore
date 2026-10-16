// Stand-in for the grading service, for browser tests of ExAutoScore
//
//   POST /assignment          like service_assignment_url (build an assignment)
//   POST /task                like service_task_url (run a submission or the example)
//   POST /_control/next       JSON with the next result: {points, success, instant_status,
//                             instant_message, protected_status, protected_feedback_html,
//                             protected_feedback_text, phase, delay_ms, reject}
//   GET  /_control/requests   requests received so far (fields and file names, no key)
//   POST /_control/reset      forget everything
//
// Results are posted back to the return address with the api key in X-Autocor-Key,
// as the real service does.

import http from 'node:http';
import { randomUUID } from 'node:crypto';

const PORT = Number(process.env.MOCK_PORT ?? 8099);
const HOST = process.env.MOCK_HOST ?? '127.0.0.1';

let assignments = new Map();
let requests = [];
let queued = [];

const defaults = {
  success: true,
  points: 10,
  task_returncode: 0,
  task_time: 1.5,
  instant_status: 'passed',
  instant_message: 'Mock: alles bestanden',
  protected_status: 'passed',
  protected_feedback_text: 'Mock-Feedback',
  protected_feedback_html: '<p><strong>Mock-Feedback</strong></p><table><tr><td>Test 1</td><td>ok</td></tr></table>',
  delay_ms: 500,
};

function json(res, code, body) {
  res.writeHead(code, { 'Content-Type': 'application/json' });
  res.end(JSON.stringify(body));
}

async function readForm(req) {
  const chunks = [];
  for await (const chunk of req) chunks.push(chunk);
  const body = Buffer.concat(chunks);
  const type = req.headers['content-type'] ?? '';
  if (type.startsWith('multipart/form-data') || type.startsWith('application/x-www-form-urlencoded')) {
    const form = await new Request('http://mock' + req.url, { method: 'POST', headers: { 'content-type': type }, body }).formData();
    const fields = {};
    const files = [];
    for (const [name, value] of form.entries()) {
      if (typeof value === 'string') fields[name] = value;
      else files.push({ field: name, name: value.name, size: value.size });
    }
    return { fields, files };
  }
  return { fields: body.length ? JSON.parse(body.toString()) : {}, files: [] };
}

async function postResult(address, apiKey, result) {
  try {
    const res = await fetch(address, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-Autocor-Key': apiKey ?? '' },
      body: JSON.stringify(result),
    });
    log(`result -> ${address} HTTP ${res.status}`);
  } catch (e) {
    log(`result -> ${address} failed: ${e.message}`);
  }
}

function log(message) {
  console.log(new Date().toISOString(), message);
}

function nextResult() {
  return { ...defaults, ...(queued.shift() ?? {}) };
}

const server = http.createServer(async (req, res) => {
  try {
    const path = new URL(req.url, 'http://mock').pathname;

    if (req.method === 'POST' && path === '/_control/next') {
      const { fields } = await readForm(req);
      queued.push(fields);
      return json(res, 200, { queued: queued.length });
    }
    if (req.method === 'GET' && path === '/_control/requests') {
      return json(res, 200, requests);
    }
    if (req.method === 'POST' && path === '/_control/reset') {
      assignments = new Map();
      requests = [];
      queued = [];
      return json(res, 200, { reset: true });
    }

    if (req.method === 'POST' && (path === '/assignment' || path === '/task')) {
      const { fields, files } = await readForm(req);
      const { api_key, ...visible } = fields;
      requests.push({ path, time: new Date().toISOString(), fields: visible, has_api_key: Boolean(api_key), files });

      const result = nextResult();
      if (result.reject) {
        return json(res, 200, { success: false, error: String(result.reject) });
      }

      if (path === '/assignment') {
        const uuid = fields.uuid || randomUUID();
        assignments.set(uuid, { returnAddress: fields.return_address, apiKey: api_key });
        log(`assignment ${uuid} "${fields.name ?? ''}"`);
        json(res, 200, { success: true, assignment_uuid: uuid, message: 'Mock: Aufgabe angelegt' });
        if (result.phase === 'build') {
          setTimeout(() => postResult(fields.return_address, api_key, {
            assignment_uuid: uuid, phase: 'build', success: false,
            instant_status: 'failed', instant_message: result.instant_message,
            debug_logs: 'Mock: build failed',
          }), result.delay_ms);
        }
        return;
      }

      const assignment = assignments.get(fields.assignment);
      if (!assignment) {
        return json(res, 200, { success: false, error: 'Mock: unknown assignment ' + (fields.assignment ?? '') });
      }
      const taskUuid = randomUUID();
      log(`task ${taskUuid} for ${fields.user_identifier ?? '?'} (${files.map(f => f.name).join(', ')})`);
      json(res, 200, { success: true, task_uuid: taskUuid, message: 'Mock: Abgabe angenommen' });
      setTimeout(() => postResult(assignment.returnAddress, assignment.apiKey, {
        task_uuid: taskUuid,
        assignment_uuid: fields.assignment,
        success: result.success,
        points: result.points,
        task_returncode: result.task_returncode,
        task_time: result.task_time,
        instant_status: result.instant_status,
        instant_message: result.instant_message,
        protected_status: result.protected_status,
        protected_feedback_text: result.protected_feedback_text,
        protected_feedback_html: result.protected_feedback_html,
        debug_logs: `[${new Date().toISOString().replace('T', ' ').replace('Z', '').replace('.', ',')}: INFO] Mock-Lauf`,
      }), result.delay_ms);
      return;
    }

    json(res, 404, { error: 'not found' });
  } catch (e) {
    log(`error: ${e.stack}`);
    json(res, 500, { error: e.message });
  }
});

server.listen(PORT, HOST, () => log(`autocor mock on http://${HOST}:${PORT}`));
