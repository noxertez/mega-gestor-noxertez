const sqlite3 = require('C:/Users/usuario/AppData/Roaming/npm/node_modules/sqlite3').verbose() || require('sqlite3').verbose();
const db = new sqlite3.Database('C:/Users/usuario/.n8n/database.sqlite');

db.all("SELECT id, name, active FROM workflow_entity WHERE active = 1", (err, rows) => {
    if (err) { console.error(err); return; }
    console.log("--- FLUJOS ACTIVOS EN BD ---");
    console.log(JSON.stringify(rows, null, 2));
    
    db.all("SELECT * FROM webhook_entity", (err, webhooks) => {
        if (err) { console.error(err); return; }
        console.log("--- WEBHOOKS REGISTRADOS ---");
        console.log(JSON.stringify(webhooks, null, 2));
        db.close();
    });
});
