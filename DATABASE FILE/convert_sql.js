const fs = require('fs');
const path = require('path');

const inputPath = path.join(__dirname, 'ecommerceweb.sql');
const outputPath = path.join(__dirname, 'init.sql');

if (!fs.existsSync(inputPath)) {
    console.error(`Input file not found at: ${inputPath}`);
    process.exit(1);
}

const content = fs.readFileSync(inputPath, 'utf8');

const tables = {}; // table_name -> { columns: [ { name, raw } ], pkey: string, autoinc: string }
const inserts = []; // array of converted insert strings

// 1. Parse CREATE TABLE statements
const createTableRegex = /CREATE TABLE\s+`(\w+)`\s*\(([\s\S]+?)\)\s*(?:ENGINE|DEFAULT CHARSET|COLLATE|AUTO_INCREMENT|DEFAULT)*\s*=[^;]+;\s*(?=\r?\n|$)/gi;
let match;
while ((match = createTableRegex.exec(content)) !== null) {
    const tableName = match[1];
    const columnsText = match[2];
    
    tables[tableName] = {
        columns: [],
        pkey: null,
        autoinc: null
    };

    const colLines = columnsText.split('\n');
    for (let colLine of colLines) {
        const trimmed = colLine.trim();
        if (!trimmed) continue;
        
        const colMatch = trimmed.match(/^`(\w+)`\s+(.+)$/);
        if (colMatch) {
            const name = colMatch[1];
            let raw = colMatch[2];
            if (raw.endsWith(',')) {
                raw = raw.slice(0, -1).trim();
            }
            tables[tableName].columns.push({
                name: name,
                raw: raw
            });
        }
    }
}

// 2. Parse ALTER TABLE statements
const alterTableRegex = /ALTER TABLE[\s\S]+?;\s*(?=\r?\n|$)/gi;
let alterMatch;
while ((alterMatch = alterTableRegex.exec(content)) !== null) {
    // Normalize newlines and spaces
    const normalized = alterMatch[0].replace(/\r?\n/g, ' ').replace(/\s+/g, ' ').trim();
    
    // Check for ADD PRIMARY KEY
    const pkeyMatch = normalized.match(/ALTER TABLE\s+`(\w+)`\s+ADD\s+PRIMARY\s+KEY\s+\(`(\w+)`\);/i);
    if (pkeyMatch) {
        const table = pkeyMatch[1];
        const col = pkeyMatch[2];
        if (tables[table]) {
            tables[table].pkey = col;
        }
        continue;
    }

    // Check for MODIFY ... AUTO_INCREMENT
    const autoincMatch = normalized.match(/ALTER TABLE\s+`(\w+)`\s+MODIFY\s+`(\w+)`\s+([^;]+)AUTO_INCREMENT/i);
    if (autoincMatch) {
        const table = autoincMatch[1];
        const col = autoincMatch[2];
        if (tables[table]) {
            tables[table].autoinc = col;
        }
        continue;
    }
}

// 3. Parse INSERT INTO statements
// We look for semicolons followed directly by a newline or end of file to prevent splitting on semicolons inside string literals (e.g. style="border:0;" or allow="autoplay; ...")
const insertRegex = /INSERT INTO[\s\S]+?;\s*(?=\r?\n|$)/gi;
let insertMatch;
while ((insertMatch = insertRegex.exec(content)) !== null) {
    inserts.push(insertMatch[0]);
}

// Now convert and output SQL
const outputLines = [];

outputLines.push('-- Converted PostgreSQL Database Dump');
outputLines.push('SET standard_conforming_strings = on;\n');

for (const tableName in tables) {
    const table = tables[tableName];
    outputLines.push(`DROP TABLE IF EXISTS ${tableName} CASCADE;`);
    outputLines.push(`CREATE TABLE ${tableName} (`);

    const colLines = table.columns.map(col => {
        let typeStr = col.raw;

        // Is this the auto-incrementing primary key?
        if (col.name === table.autoinc || col.name === table.pkey) {
            typeStr = 'SERIAL PRIMARY KEY';
        } else {
            // Clean up types
            typeStr = typeStr
                .replace(/int\(\d+\)/g, 'INTEGER')
                .replace(/int\s+NOT/gi, 'INTEGER NOT')
                .replace(/double/gi, 'DOUBLE PRECISION')
                .replace(/float/gi, 'REAL')
                .replace(/longtext/gi, 'TEXT')
                .replace(/datetime/gi, 'TIMESTAMP')
                .replace(/CHARACTER SET \w+/gi, '')
                .replace(/COLLATE \w+/gi, '')
                .trim();
        }

        return `    ${col.name} ${typeStr}`;
    });

    outputLines.push(colLines.join(',\n'));
    outputLines.push(');\n');
}

// Convert inserts
for (let insert of inserts) {
    // 1. Remove backticks from table and column names
    let converted = insert.replace(/`(\w+)`/g, '$1');

    // 2. Escape single quotes inside single-quoted strings:
    // MySQL uses \' for single quote inside single-quoted string.
    // In PostgreSQL, we should convert it to '' (two single quotes).
    converted = converted.replace(/\\'/g, "''");

    // 3. Replace escaped double quotes:
    // MySQL uses \" inside single-quoted string for double quote.
    // In PostgreSQL, we can just use " without backslash escape.
    converted = converted.replace(/\\"/g, '"');

    // 4. Convert literal carriage returns \r\n to actual newlines
    converted = converted.replace(/\\r\\n/g, '\r\n');
    converted = converted.replace(/\\n/g, '\n');

    outputLines.push(converted);
}

outputLines.push('\n-- Resetting sequence values for SERIAL columns to prevent unique key violations');
for (const tableName in tables) {
    const table = tables[tableName];
    const serialCol = table.autoinc || table.pkey;
    if (serialCol) {
        outputLines.push(`SELECT setval(pg_get_serial_sequence('${tableName}', '${serialCol}'), COALESCE(max(${serialCol}), 1)) FROM ${tableName};`);
    }
}

fs.writeFileSync(outputPath, outputLines.join('\n'), 'utf8');
console.log('PostgreSQL migration file created at:', outputPath);
