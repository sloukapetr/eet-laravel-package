# Agent Instructions for eet-laravel-package

Laravel package for the Czech EET 2.0 data interface and CA EET certificate renewal.

## Source of Truth
- `docs/EETXMLSchema.xsd` and `docs/EETServiceSOAP.wsdl` define the namespace, `SOAPAction`, and element names.
- `docs/caeetapi/caeetapi_jwt.yml` defines CAEET renewal.
- `tests/` is the contract for supported behavior.
- Detailed rules (Czech) are in `.ai/rules/package.md`. They take precedence over this summary.

## Working Rules
- PHP `^8.5`, Laravel `^13`. No migrations, models, queues, or application policy.
- Amounts are `int` minor units. Never use float math.
- Never log a certificate, a password, a private key, or XML that contains `BinarySecurityToken`.
- Tests must not call the network.

## Do Not
- Do not invent XML names that are not in the XSD or WSDL.
- Do not commit playground certificate passwords or `docs/CAEET_Playground_2026_v1.zip`.
- Do not commit or tag. The maintainer does that.
