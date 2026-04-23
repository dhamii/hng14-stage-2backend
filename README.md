# Profiles API

This project contains a RESTful API for managing user profiles, including a rule-based natural language query parser.

## Natural Language Query Parsing (`/api/profiles/search`)

The search endpoint accepts natural language queries via the `q` parameter and converts them into precise filters using a rule-based parsing engine.

### Approach & Parsing Logic
The `QueryParser` service processes plain English text to identify supported attributes and map them to database filters. Its core mechanism relies on finding specific patterns and keywords within the query string.
The parser does **not** use AI or LLMs. It is entirely deterministic and relies on predefined regex and string matching rules.

### Supported Keywords and Mappings

1. **Gender Patterns:**
   - **Keywords:** `male`, `males`, `female`, `females`
   - **Mapping:** Translates directly to `gender=male` or `gender=female`.

2. **Age Groups:**
   - **Keywords:** `child`, `teenager`, `adult`, `senior`
   - **Mapping:** Translates to the exact `age_group` column value.

3. **"Young" Alias:**
   - **Keywords:** `young`
   - **Mapping:** Internally maps to an age range, setting `min_age=16` and `max_age=24`. It is not stored as an age group but treated as an age constraint.

4. **Age Boundaries:**
   - **Keywords:** `above X`, `over X`, `X and above`
   - **Mapping:** Sets `min_age=X`.
   - **Keywords:** `below X`, `under X`, `less than X`
   - **Mapping:** Sets `max_age=X`.

5. **Countries:**
   - **Keywords:** `from [country_name]`
   - **Mapping:** Looks up `[country_name]` in a predefined map (`nigeria`, `angola`, `kenya`, `benin`, `ghana`, `south africa`, `egypt`) and translates it to its 2-letter ISO code (`country_id=NG`, etc.).

### How the Logic Works
- The query string is converted to lowercase.
- Independent rule sets (Regex, `str_contains`) are evaluated sequentially.
- If a match is found for a specific attribute, the corresponding filter (e.g., `gender`, `min_age`, `country_id`) is appended to an array.
- The resulting filter array is then merged into the original request instance, allowing the standard `index` controller logic to apply standard Eloquent scope methods based on the generated keys.
- If no predefined rules match, it throws a `400 Bad Request` with the message "Unable to interpret query".

---

## Limitations and Edge Cases

Because the parser is strictly rule-based, it comes with several limitations:

1. **Complex Boolean Logic (AND/OR/NOT):**
   - The parser assumes all identified criteria are combined with an implicit `AND`.
   - It cannot handle exclusionary logic (e.g., "not from Nigeria" or "males except adults").
   - It cannot handle OR conditions smoothly (e.g., "males or females from Kenya" will likely just apply the last parsed gender). 
   - A query like "male and female teenagers above 17" only applies the last matching gender (female) or might fail depending on regex order, though age group (teenager) and boundary (`min_age=17`) apply correctly.

2. **Vocabulary Restraint:**
   - Synonyms are not handled unless explicitly programmed. For example, "boys" or "guys" will not evaluate to `gender=male`.
   - Words like "aged", "years old", or complex sentence structures are ignored.

3. **Country Name Parsing:**
   - Only a hardcoded dictionary of countries is supported. If a country is not in the `countryMap`, the "from X" query fails to map a `country_id`.
   - Misspellings or abbreviations (e.g., "Naija" or "US") are not recognized.

4. **Multiple Age Boundaries:**
   - If a query contains multiple identical boundaries (e.g., "above 20 and above 30"), it will overwrite the previous mapping, using only one boundary.
   - The alias "young" sets its own `min_age` and `max_age`. If combined with "above 18" ("young males above 18"), it can result in conflicting constraints depending on the execution order.
