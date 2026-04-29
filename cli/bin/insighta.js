#!/usr/bin/env node

import fs from "fs";
import os from "os";
import path from "path";
import http from "http";
import crypto from "crypto";
import { Command } from "commander";
import axios from "axios";
import open from "open";
import ora from "ora";
import Table from "cli-table3";

const program = new Command();
const backendBaseUrl = process.env.INSIGHTA_API_BASE_URL || "http://localhost:8000/api";
const credDir = path.join(os.homedir(), ".insighta");
const credFile = path.join(credDir, "credentials.json");
const apiVersionHeader = { "X-API-Version": "1" };

const ensureCredentialStore = () => {
  if (!fs.existsSync(credDir)) fs.mkdirSync(credDir, { recursive: true });
};

const readCreds = () => {
  if (!fs.existsSync(credFile)) return null;
  return JSON.parse(fs.readFileSync(credFile, "utf8"));
};

const writeCreds = (creds) => {
  ensureCredentialStore();
  fs.writeFileSync(credFile, JSON.stringify(creds, null, 2));
};

const clearCreds = () => {
  if (fs.existsSync(credFile)) fs.unlinkSync(credFile);
};

const codeVerifier = () => crypto.randomBytes(32).toString("base64url");
const challengeFromVerifier = (verifier) =>
  crypto.createHash("sha256").update(verifier).digest("base64url");

const api = async (method, url, data, auth = true) => {
  const creds = readCreds();
  const headers = { ...apiVersionHeader };
  if (auth && creds?.tokens?.access_token) headers.Authorization = `Bearer ${creds.tokens.access_token}`;

  try {
    return await axios({ method, url: `${backendBaseUrl}${url}`, data, headers });
  } catch (error) {
    if (error.response?.status === 401 && auth && creds?.tokens?.refresh_token) {
      const refreshed = await axios.post(`${backendBaseUrl}/auth/refresh`, {
        refresh_token: creds.tokens.refresh_token,
      });
      creds.tokens = refreshed.data.tokens;
      writeCreds(creds);
      headers.Authorization = `Bearer ${creds.tokens.access_token}`;
      return axios({ method, url: `${backendBaseUrl}${url}`, data, headers });
    }
    throw error;
  }
};

program.name("insighta").description("Insighta Labs+ CLI");

program
  .command("login")
  .description("Authenticate with GitHub OAuth + PKCE")
  .action(async () => {
    const spinner = ora("Starting OAuth login").start();
    const verifier = codeVerifier();
    const challenge = challengeFromVerifier(verifier);
    const callbackPort = 8965;
    const localRedirect = `http://localhost:${callbackPort}/callback`;

    const authUrl = `${backendBaseUrl}/auth/github?code_challenge=${encodeURIComponent(challenge)}&code_challenge_method=S256&redirect_uri=${encodeURIComponent(localRedirect)}`;
    const server = http.createServer(async (req, res) => {
      if (!req.url.startsWith("/callback")) return;
      const reqUrl = new URL(req.url, localRedirect);
      const code = reqUrl.searchParams.get("code");
      const state = reqUrl.searchParams.get("state");
      try {
        const callback = await axios.get(`${backendBaseUrl}/auth/github/callback`, {
          params: { code, state, code_verifier: verifier },
        });
        writeCreds({ user: callback.data.user, tokens: callback.data.tokens });
        res.end("Login successful. You can close this window.");
      } catch {
        res.statusCode = 400;
        res.end("Login failed.");
      } finally {
        server.close();
        spinner.succeed("Authenticated");
      }
    });

    server.listen(callbackPort);
    spinner.text = "Opening browser for GitHub login";
    await open(authUrl);
  });

program.command("logout").description("Logout and invalidate refresh token").action(async () => {
  const creds = readCreds();
  if (creds?.tokens?.refresh_token) {
    await axios.post(`${backendBaseUrl}/auth/logout`, { refresh_token: creds.tokens.refresh_token }).catch(() => null);
  }
  clearCreds();
  process.stdout.write("Logged out\n");
});

program.command("whoami").description("Show current user").action(() => {
  const creds = readCreds();
  if (!creds?.user) return process.stdout.write("Not logged in\n");
  process.stdout.write(`${creds.user.username} (${creds.user.role})\n`);
});

const profiles = program.command("profiles").description("Profile operations");

profiles.command("list").action(async () => {
  const spinner = ora("Fetching profiles").start();
  const response = await api("get", "/profiles");
  spinner.stop();
  const table = new Table({ head: ["ID", "Name", "Gender", "Age", "Country"] });
  response.data.data.forEach((p) => table.push([p.id, p.name, p.gender, p.age, p.country_id]));
  process.stdout.write(`${table.toString()}\n`);
});

profiles.command("get").argument("<id>").action(async (id) => {
  const response = await api("get", `/profiles?limit=1&page=1&id=${encodeURIComponent(id)}`);
  process.stdout.write(`${JSON.stringify(response.data, null, 2)}\n`);
});

profiles.command("search").argument("<query>").action(async (query) => {
  const response = await api("get", `/profiles/search?q=${encodeURIComponent(query)}`);
  process.stdout.write(`${JSON.stringify(response.data, null, 2)}\n`);
});

profiles.command("create").argument("<name>").action(async (name) => {
  const response = await api("post", "/profiles", { name });
  process.stdout.write(`${JSON.stringify(response.data, null, 2)}\n`);
});

profiles.command("export").option("--format <format>", "Export format", "csv").action(async (opts) => {
  const response = await api("get", `/profiles/export?format=${opts.format}`);
  process.stdout.write(`${response.data}\n`);
});

program.parseAsync(process.argv);
