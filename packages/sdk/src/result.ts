// Railway Oriented Programming — Result<T, E>
//
// Two tracks: Ok (success) and Err (failure).
// Functions compose without try/catch by threading Results through the pipeline.

export type Ok<A> = { readonly _tag: 'Ok'; readonly value: A }
export type Err<E> = { readonly _tag: 'Err'; readonly error: E }
export type Result<A, E = never> = Ok<A> | Err<E>

export const ok = <A>(value: A): Ok<A> => ({ _tag: 'Ok', value })
export const err = <E>(error: E): Err<E> => ({ _tag: 'Err', error })

export const isOk = <A, E>(r: Result<A, E>): r is Ok<A> => r._tag === 'Ok'
export const isErr = <A, E>(r: Result<A, E>): r is Err<E> => r._tag === 'Err'

/** Transform the Ok value, pass Err through unchanged. */
export const map = <A, B, E>(r: Result<A, E>, f: (a: A) => B): Result<B, E> =>
  isOk(r) ? ok(f(r.value)) : r

/** Chain an Ok value into another Result-returning function. */
export const flatMap = <A, B, E>(r: Result<A, E>, f: (a: A) => Result<B, E>): Result<B, E> =>
  isOk(r) ? f(r.value) : r

/** Transform the Err value, pass Ok through unchanged. */
export const mapErr = <A, E, F>(r: Result<A, E>, f: (e: E) => F): Result<A, F> =>
  isErr(r) ? err(f(r.error)) : r

/** Unwrap the Ok value, or return the fallback for Err. */
export const getOrElse = <A, E>(r: Result<A, E>, fallback: A): A =>
  isOk(r) ? r.value : fallback
