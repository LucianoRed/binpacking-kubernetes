import { Server } from "@modelcontextprotocol/sdk/server/index.js";
import { StdioServerTransport } from "@modelcontextprotocol/sdk/server/stdio.js";
import { CallToolRequestSchema, ListToolsRequestSchema } from "@modelcontextprotocol/sdk/types.js";
import fs from "fs/promises";
import path from "path";
import { fileURLToPath } from 'url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

// Define path to data file - assuming running from root or src
const DATA_FILE = process.env.DATA_FILE || path.resolve(__dirname, '../data/students.json');

console.error(`Using data file: ${DATA_FILE}`);

const server = new Server(
  {
    name: "mcp-server-matriculas",
    version: "1.0.0",
  },
  {
    capabilities: {
      tools: {},
    },
  }
);

async function readData() {
  try {
    const data = await fs.readFile(DATA_FILE, "utf-8");
    return JSON.parse(data);
  } catch (error) {
    console.error("Error reading data file:", error);
    return [];
  }
}

async function writeData(data) {
  await fs.writeFile(DATA_FILE, JSON.stringify(data, null, 4), "utf-8");
}

server.setRequestHandler(ListToolsRequestSchema, async () => {
  return {
    tools: [
      {
        name: "list_students",
        description: "List all registered students",
        inputSchema: {
          type: "object",
          properties: {},
        },
      },
      {
        name: "search_student",
        description: "Search for a student by name",
        inputSchema: {
          type: "object",
          properties: {
            query: {
              type: "string",
              description: "Name or partial name to search for",
            },
          },
          required: ["query"],
        },
      },
      {
        name: "register_student",
        description: "Register a new student",
        inputSchema: {
          type: "object",
          properties: {
            name: {
              type: "string",
              description: "Full name of the student",
            },
            dob: {
              type: "string",
              description: "Date of birth (YYYY-MM-DD)",
            },
            year: {
              type: "string",
              description: "School year desired (e.g. 2024)",
            },
          },
          required: ["name", "dob", "year"],
        },
      },
    ],
  };
});

server.setRequestHandler(CallToolRequestSchema, async (request) => {
  const { name, arguments: args } = request.params;

  try {
    const students = await readData();

    if (name === "list_students") {
      return {
        content: [
          {
            type: "text",
            text: JSON.stringify(students, null, 2),
          },
        ],
      };
    } else if (name === "search_student") {
      const query = args.query.toLowerCase();
      const results = students.filter((s) =>
        s.name.toLowerCase().includes(query)
      );
      return {
        content: [
          {
            type: "text",
            text: JSON.stringify(results, null, 2),
          },
        ],
      };
    } else if (name === "register_student") {
      const newId = students.length > 0 ? Math.max(...students.map(s => s.id)) + 1 : 1;
      const newStudent = {
        id: newId,
        name: args.name,
        dob: args.dob,
        year: args.year,
        created_at: new Date().toISOString().replace('T', ' ').substring(0, 19)
      };
      
      students.push(newStudent);
      await writeData(students);
      
      return {
        content: [
          {
            type: "text",
            text: `Student registered successfully: ${JSON.stringify(newStudent, null, 2)}`,
          },
        ],
      };
    } else {
      throw new Error(`Unknown tool: ${name}`);
    }
  } catch (error) {
    return {
      content: [
        {
          type: "text",
          text: `Error: ${error.message}`,
        },
      ],
      isError: true,
    };
  }
});

const transport = new StdioServerTransport();
await server.connect(transport);
