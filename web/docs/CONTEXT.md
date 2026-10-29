# Smart E-Waste Web Application

Shared account-management vocabulary for student and administrator access.

## Language

**Activated account**:
A user account with a password set by the user or an administrator.
_Avoid_: Active account

**Unactivated account**:
A user account for which no password has been set.
_Avoid_: Inactive account

**Active account**:
A user account enabled by an administrator for use of the collection system.
_Avoid_: Activated account

**Inactive account**:
A user account disabled by an administrator.
_Avoid_: Unactivated account

**Password setup**:
The initial assignment of a password to an account an admin created.
_Avoid_: Registration

**Registration**:
A student creating their own account at `/register`. There is no admin approval;
the account is usable once the student confirms their email.
_Avoid_: Sign-up, Password setup

**Email confirmation**:
The student opening the link sent to their email after registering, which sets
`email_verified_at`.
_Avoid_: Activation

**Inactive organization**:
An organization closed to new student assignments while its existing memberships
are retained.
_Avoid_: Inactive account
