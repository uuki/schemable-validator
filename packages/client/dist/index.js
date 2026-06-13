//#region src/result.ts
const ok = (value) => ({
	_tag: "Ok",
	value
});
const err = (error) => ({
	_tag: "Err",
	error
});
const isOk = (r) => r._tag === "Ok";
const isErr = (r) => r._tag === "Err";
/** Transform the Ok value, pass Err through unchanged. */
const map = (r, f) => isOk(r) ? ok(f(r.value)) : r;
/** Chain an Ok value into another Result-returning function. */
const flatMap = (r, f) => isOk(r) ? f(r.value) : r;
/** Transform the Err value, pass Ok through unchanged. */
const mapErr = (r, f) => isErr(r) ? err(f(r.error)) : r;
/** Unwrap the Ok value, or return the fallback for Err. */
const getOrElse = (r, fallback) => isOk(r) ? r.value : fallback;
//#endregion
//#region src/constraint.ts
const append = (state, message) => ({
	...state,
	errors: [...state.errors, message]
});
const composeConstraints = (constraints) => (state) => constraints.reduce((s, c) => c(s), state);
const FORMAT_RE = {
	email: /^[^\s@]+@[^\s@]+\.[^\s@]+$/,
	uri: /^https?:\/\/[^\s]+$/
};
const checkType = (type) => (state) => {
	if (!type) return state;
	const primary = (Array.isArray(type) ? type : [type]).find((t) => t !== "null");
	if (!primary) return state;
	if (primary === "integer") {
		const n = Number(state.value);
		if (!Number.isFinite(n) || !Number.isInteger(n)) return append(state, "must be an integer");
	} else if (primary === "number") {
		if (!Number.isFinite(Number(state.value))) return append(state, "must be a number");
	} else if (primary === "boolean") {
		if (!new Set([
			"true",
			"false",
			"1",
			"0",
			"on",
			"off",
			"yes",
			"no"
		]).has(state.value.toLowerCase())) return append(state, "must be a boolean");
	}
	return state;
};
const checkMinLength = (min) => (state) => state.value.length >= min ? state : append(state, `must be at least ${min} character${min !== 1 ? "s" : ""} long`);
const checkMaxLength = (max) => (state) => state.value.length <= max ? state : append(state, `must be no more than ${max} character${max !== 1 ? "s" : ""} long`);
const checkMinimum = (min) => (state) => {
	const n = Number(state.value);
	return Number.isFinite(n) && n >= min ? state : append(state, `must be at least ${min}`);
};
const checkMaximum = (max) => (state) => {
	const n = Number(state.value);
	return Number.isFinite(n) && n <= max ? state : append(state, `must be no more than ${max}`);
};
const checkFormat = (format) => (state) => {
	const re = FORMAT_RE[format];
	if (!re) return state;
	return re.test(state.value) ? state : append(state, `must be a valid ${format}`);
};
const checkPattern = (pattern) => (state) => {
	try {
		return new RegExp(pattern, "u").test(state.value) ? state : append(state, "must match the required format");
	} catch {
		return state;
	}
};
const checkEnum = (values) => (state) => values.includes(state.value) ? state : append(state, `must be one of: ${values.join(", ")}`);
const constraintsFromSchema = (schema) => {
	const cs = [];
	if (schema.type !== void 0) cs.push(checkType(schema.type));
	if (schema.minLength !== void 0) cs.push(checkMinLength(schema.minLength));
	if (schema.maxLength !== void 0) cs.push(checkMaxLength(schema.maxLength));
	if (schema.minimum !== void 0) cs.push(checkMinimum(schema.minimum));
	if (schema.maximum !== void 0) cs.push(checkMaximum(schema.maximum));
	if (schema.format !== void 0) cs.push(checkFormat(schema.format));
	if (schema.pattern !== void 0) cs.push(checkPattern(schema.pattern));
	if (schema.enum !== void 0) cs.push(checkEnum(schema.enum));
	return composeConstraints(cs);
};
//#endregion
//#region src/validator.ts
const validateField = (value, schema, required) => {
	const isEmpty = value === "";
	if (required && isEmpty) return err(["is required"]);
	if (isEmpty) return ok(value);
	const initial = {
		value,
		errors: []
	};
	const final = constraintsFromSchema(schema)(initial);
	return final.errors.length === 0 ? ok(value) : err(final.errors);
};
const toFieldResult = (value, result) => result._tag === "Ok" ? {
	value,
	is_valid: true,
	errors: null
} : {
	value,
	is_valid: false,
	errors: result.error
};
const validateObject = (data, schema) => {
	const required = schema.required ?? [];
	const unmapped = schema["x-unmapped-fields"] ?? [];
	const result = {};
	for (const [name, fieldSchema] of Object.entries(schema.properties)) {
		const value = data[name] ?? "";
		result[name] = toFieldResult(value, validateField(value, fieldSchema, required.includes(name)));
	}
	for (const name of unmapped) result[name] = {
		value: data[name] ?? "",
		is_valid: true,
		errors: null
	};
	return result;
};
const isAllValid = (result) => Object.values(result).every((f) => f.is_valid);
/** Return only the fields that failed, keyed by name. */
const extractErrors = (result) => {
	const errors = {};
	for (const [name, field] of Object.entries(result)) if (!field.is_valid && field.errors !== null) errors[name] = field.errors;
	return errors;
};
//#endregion
export { checkEnum, checkFormat, checkMaxLength, checkMaximum, checkMinLength, checkMinimum, checkPattern, checkType, composeConstraints, constraintsFromSchema, err, extractErrors, flatMap, getOrElse, isAllValid, isErr, isOk, map, mapErr, ok, validateObject };

//# sourceMappingURL=index.js.map