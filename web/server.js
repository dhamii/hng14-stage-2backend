import express from "express";
import helmet from "helmet";
import cookieParser from "cookie-parser";
import csrf from "csurf";

const app = express();
const port = process.env.PORT || 3001;
const backend = process.env.INSIGHTA_API_BASE_URL || "http://localhost:8000/api";

app.set("view engine", "ejs");
app.set("views", new URL("./views", import.meta.url).pathname);
app.use(helmet());
app.use(express.urlencoded({ extended: true }));
app.use(cookieParser());
app.use(csrf({ cookie: { httpOnly: true, sameSite: "lax" } }));

app.get("/login", (req, res) => {
  res.render("login", { csrfToken: req.csrfToken(), backend });
});

app.get("/dashboard", (req, res) => res.render("dashboard"));
app.get("/profiles", (req, res) => res.render("profiles"));
app.get("/profiles/:id", (req, res) => res.render("profile-detail", { id: req.params.id }));
app.get("/search", (req, res) => res.render("search"));
app.get("/account", (req, res) => res.render("account"));

app.listen(port, () => {
  process.stdout.write(`Insighta web running on :${port}\n`);
});
