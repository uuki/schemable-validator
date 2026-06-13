//#region src/result.d.ts
type Ok<A> = {
  readonly _tag: 'Ok';
  readonly value: A;
};
type Err<E> = {
  readonly _tag: 'Err';
  readonly error: E;
};
type Result<A, E = never> = Ok<A> | Err<E>;
declare const ok: <A>(value: A) => Ok<A>;
declare const err: <E>(error: E) => Err<E>;
declare const isOk: <A, E>(r: Result<A, E>) => r is Ok<A>;
declare const isErr: <A, E>(r: Result<A, E>) => r is Err<E>;
/** Transform the Ok value, pass Err through unchanged. */
declare const map: <A, B, E>(r: Result<A, E>, f: (a: A) => B) => Result<B, E>;
/** Chain an Ok value into another Result-returning function. */
declare const flatMap: <A, B, E>(r: Result<A, E>, f: (a: A) => Result<B, E>) => Result<B, E>;
/** Transform the Err value, pass Ok through unchanged. */
declare const mapErr: <A, E, F>(r: Result<A, E>, f: (e: E) => F) => Result<A, F>;
/** Unwrap the Ok value, or return the fallback for Err. */
declare const getOrElse: <A, E>(r: Result<A, E>, fallback: A) => A;
//#endregion
//#region src/schema.d.ts
type JsonSchemaType = 'string' | 'integer' | 'number' | 'boolean' | 'null';
/** Per-field schema fragment, as emitted in the "properties" block. */
type PropertySchema = {
  readonly type?: JsonSchemaType | readonly JsonSchemaType[];
  readonly minLength?: number;
  readonly maxLength?: number;
  readonly format?: 'email' | 'uri';
  readonly pattern?: string;
  readonly enum?: readonly string[];
  readonly minimum?: number;
  readonly maximum?: number;
};
/** Top-level JSON Schema object produced by SchemaBuilder. */
type ObjectSchema = {
  readonly $schema: string;
  readonly type: 'object';
  readonly properties: Readonly<Record<string, PropertySchema>>;
  readonly required?: readonly string[];
  readonly 'x-unmapped-fields'?: readonly string[];
};
//#endregion
//#region src/constraint.d.ts
type FieldState = {
  readonly value: string;
  readonly errors: readonly string[];
};
type Constraint = (state: FieldState) => FieldState;
declare const composeConstraints: (constraints: readonly Constraint[]) => Constraint;
declare const checkType: (type: PropertySchema["type"]) => Constraint;
declare const checkMinLength: (min: number) => Constraint;
declare const checkMaxLength: (max: number) => Constraint;
declare const checkMinimum: (min: number) => Constraint;
declare const checkMaximum: (max: number) => Constraint;
declare const checkFormat: (format: string) => Constraint;
declare const checkPattern: (pattern: string) => Constraint;
declare const checkEnum: (values: readonly string[]) => Constraint;
declare const constraintsFromSchema: (schema: PropertySchema) => Constraint;
//#endregion
//#region src/validator.d.ts
type FieldResult = {
  readonly value: string;
  readonly is_valid: boolean;
  readonly errors: readonly string[] | null;
};
type ValidationResult = Readonly<Record<string, FieldResult>>;
declare const validateObject: (data: Readonly<Record<string, string>>, schema: ObjectSchema) => ValidationResult;
declare const isAllValid: (result: ValidationResult) => boolean;
/** Return only the fields that failed, keyed by name. */
declare const extractErrors: (result: ValidationResult) => Readonly<Record<string, readonly string[]>>;
//#endregion
export { type Constraint, type Err, type FieldResult, type FieldState, type JsonSchemaType, type ObjectSchema, type Ok, type PropertySchema, type Result, type ValidationResult, checkEnum, checkFormat, checkMaxLength, checkMaximum, checkMinLength, checkMinimum, checkPattern, checkType, composeConstraints, constraintsFromSchema, err, extractErrors, flatMap, getOrElse, isAllValid, isErr, isOk, map, mapErr, ok, validateObject };
//# sourceMappingURL=index-BKa_EsyB.d.cts.map