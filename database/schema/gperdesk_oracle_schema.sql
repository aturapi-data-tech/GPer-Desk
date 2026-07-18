-- ============================================================
-- GPer-Desk — Oracle DDL (generated from Laravel migrations)
-- ============================================================

-- ---- 0001_01_01_000000_create_users_table.php ----
create table "USERS" ( "ID" number(19,0) not null, "NAME" varchar2(255) not null, "EMAIL" varchar2(255) not null, "EMAIL_VERIFIED_AT" timestamp null, "PASSWORD" varchar2(255) not null, "REMEMBER_TOKEN" varchar2(100) null, "CREATED_AT" timestamp null, "UPDATED_AT" timestamp null, constraint users_id_pk primary key ( "ID" ) );
alter table "USERS" add constraint users_email_uk unique ( "EMAIL" );
create sequence "USERS_ID_SEQ" minvalue 1  start with 1 increment by 1;

            create trigger "USERS_ID_TRG"
            before insert on "USERS"
            for each row
                begin
            if :new."ID" is null then
                select "USERS_ID_SEQ".nextval into :new."ID" from dual;
            end if;
            end;;
create table "PASSWORD_RESET_TOKENS" ( "EMAIL" varchar2(255) not null, "TOKEN" varchar2(255) not null, "CREATED_AT" timestamp null, constraint password_reset_tokens_email_pk primary key ( "EMAIL" ) );
create table "SESSIONS" ( "ID" varchar2(255) not null, "USER_ID" number(19,0) null, "IP_ADDRESS" varchar2(45) null, "USER_AGENT" clob null, "PAYLOAD" clob not null, "LAST_ACTIVITY" number(10,0) not null, constraint sessions_id_pk primary key ( "ID" ) );
create index sessions_user_id_index on "SESSIONS" ( "USER_ID" );
create index sessions_last_activity_index on "SESSIONS" ( "LAST_ACTIVITY" );

-- ---- 0001_01_01_000001_create_cache_table.php ----
create table "CACHE" ( "KEY" varchar2(255) not null, "VALUE" clob not null, "EXPIRATION" number(10,0) not null, constraint cache_key_pk primary key ( "KEY" ) );
create index cache_expiration_index on "CACHE" ( "EXPIRATION" );
create table "CACHE_LOCKS" ( "KEY" varchar2(255) not null, "OWNER" varchar2(255) not null, "EXPIRATION" number(10,0) not null, constraint cache_locks_key_pk primary key ( "KEY" ) );
create index cache_locks_expiration_index on "CACHE_LOCKS" ( "EXPIRATION" );

-- ---- 0001_01_01_000002_create_jobs_table.php ----
create table "JOBS" ( "ID" number(19,0) not null, "QUEUE" varchar2(255) not null, "PAYLOAD" clob not null, "ATTEMPTS" number(3,0) not null, "RESERVED_AT" number(10,0) null, "AVAILABLE_AT" number(10,0) not null, "CREATED_AT" number(10,0) not null, constraint jobs_id_pk primary key ( "ID" ) );
create index jobs_queue_index on "JOBS" ( "QUEUE" );
create sequence "JOBS_ID_SEQ" minvalue 1  start with 1 increment by 1;

            create trigger "JOBS_ID_TRG"
            before insert on "JOBS"
            for each row
                begin
            if :new."ID" is null then
                select "JOBS_ID_SEQ".nextval into :new."ID" from dual;
            end if;
            end;;
create table "JOB_BATCHES" ( "ID" varchar2(255) not null, "NAME" varchar2(255) not null, "TOTAL_JOBS" number(10,0) not null, "PENDING_JOBS" number(10,0) not null, "FAILED_JOBS" number(10,0) not null, "FAILED_JOB_IDS" clob not null, "OPTIONS" clob null, "CANCELLED_AT" number(10,0) null, "CREATED_AT" number(10,0) not null, "FINISHED_AT" number(10,0) null, constraint job_batches_id_pk primary key ( "ID" ) );
create table "FAILED_JOBS" ( "ID" number(19,0) not null, "UUID" varchar2(255) not null, "CONNECTION" clob not null, "QUEUE" clob not null, "PAYLOAD" clob not null, "EXCEPTION" clob not null, "FAILED_AT" timestamp not null, constraint failed_jobs_id_pk primary key ( "ID" ) );
alter table "FAILED_JOBS" add constraint failed_jobs_uuid_uk unique ( "UUID" );
create sequence "FAILED_JOBS_ID_SEQ" minvalue 1  start with 1 increment by 1;

            create trigger "FAILED_JOBS_ID_TRG"
            before insert on "FAILED_JOBS"
            for each row
                begin
            if :new."ID" is null then
                select "FAILED_JOBS_ID_SEQ".nextval into :new."ID" from dual;
            end if;
            end;;

-- ---- 2026_01_27_091529_create_permission_tables.php ----
create table "PERMISSIONS" ( "ID" number(19,0) not null, "NAME" varchar2(255) not null, "GUARD_NAME" varchar2(255) not null, "CREATED_AT" timestamp null, "UPDATED_AT" timestamp null, constraint permissions_id_pk primary key ( "ID" ) );
alter table "PERMISSIONS" add constraint permissions_name_guard_name_uk unique ( "NAME", "GUARD_NAME" );
create sequence "PERMISSIONS_ID_SEQ" minvalue 1  start with 1 increment by 1;

            create trigger "PERMISSIONS_ID_TRG"
            before insert on "PERMISSIONS"
            for each row
                begin
            if :new."ID" is null then
                select "PERMISSIONS_ID_SEQ".nextval into :new."ID" from dual;
            end if;
            end;;
create table "ROLES" ( "ID" number(19,0) not null, "NAME" varchar2(255) not null, "GUARD_NAME" varchar2(255) not null, "CREATED_AT" timestamp null, "UPDATED_AT" timestamp null, constraint roles_id_pk primary key ( "ID" ) );
alter table "ROLES" add constraint roles_name_guard_name_uk unique ( "NAME", "GUARD_NAME" );
create sequence "ROLES_ID_SEQ" minvalue 1  start with 1 increment by 1;

            create trigger "ROLES_ID_TRG"
            before insert on "ROLES"
            for each row
                begin
            if :new."ID" is null then
                select "ROLES_ID_SEQ".nextval into :new."ID" from dual;
            end if;
            end;;
create table "MODEL_HAS_PERMISSIONS" ( "PERMISSION_ID" number(19,0) not null, "MODEL_TYPE" varchar2(255) not null, "MODEL_ID" number(19,0) not null, constraint mo_ha_permissi_permiss_id_fk foreign key ( "PERMISSION_ID" ) references "PERMISSIONS" ( "ID" ) on delete cascade, constraint mhp_perm_model_type_pk primary key ( "PERMISSION_ID", "MODEL_ID", "MODEL_TYPE" ) );
create index mhp_model_id_type_index on "MODEL_HAS_PERMISSIONS" ( "MODEL_ID", "MODEL_TYPE" );
create table "MODEL_HAS_ROLES" ( "ROLE_ID" number(19,0) not null, "MODEL_TYPE" varchar2(255) not null, "MODEL_ID" number(19,0) not null, constraint model_has_roles_role_id_fk foreign key ( "ROLE_ID" ) references "ROLES" ( "ID" ) on delete cascade, constraint mhr_role_model_type_pk primary key ( "ROLE_ID", "MODEL_ID", "MODEL_TYPE" ) );
create index mhr_model_id_type_index on "MODEL_HAS_ROLES" ( "MODEL_ID", "MODEL_TYPE" );
create table "ROLE_HAS_PERMISSIONS" ( "PERMISSION_ID" number(19,0) not null, "ROLE_ID" number(19,0) not null, constraint ro_ha_permissio_permissi_id_fk foreign key ( "PERMISSION_ID" ) references "PERMISSIONS" ( "ID" ) on delete cascade, constraint rol_ha_permission_rol_id_fk foreign key ( "ROLE_ID" ) references "ROLES" ( "ID" ) on delete cascade, constraint rhp_perm_role_pk primary key ( "PERMISSION_ID", "ROLE_ID" ) );
delete from "CACHE" where "KEY" in ('gper-desk-cache-spatie.permission.cache', 'gper-desk-cache-illuminate:cache:flexible:created:spatie.permission.cache');

-- ---- 2026_06_05_100000_alter_users_add_myuser_profesi.php ----
ALTER TABLE users ADD (myuser_profesi VARCHAR2(30));

-- ---- 2026_06_18_100001_create_tickets_table.php ----
create table "TICKETS" ( "ID" number(19,0) not null, "TICKET_NO" varchar2(30) not null, "TITLE" varchar2(255) not null, "DESCRIPTION" clob null, "CATEGORY" varchar2(50) null, "PRIORITY" varchar2(20) default 'medium' not null, "STATUS" varchar2(20) default 'open' not null, "LOCATION" varchar2(255) null, "REPORTED_BY" number(19,0) null, "TECHNICIAN_ID" number(19,0) null, "RESOLUTION" clob null, "PART_COST" number(15, 2) default '0' not null, "ATTACHMENT_PATH" varchar2(255) null, "REPORTED_AT" timestamp null, "RESOLVED_AT" timestamp null, "CREATED_AT" timestamp null, "UPDATED_AT" timestamp null, constraint tic_reported_by_fk foreign key ( "REPORTED_BY" ) references "USERS" ( "ID" ) on delete set null, constraint tic_technician_fk foreign key ( "TECHNICIAN_ID" ) references "USERS" ( "ID" ) on delete set null, constraint tickets_id_pk primary key ( "ID" ) );
alter table "TICKETS" add constraint tic_ticket_no_uq unique ( "TICKET_NO" );
create index tic_status_idx on "TICKETS" ( "STATUS" );
create index tic_category_idx on "TICKETS" ( "CATEGORY" );
create sequence "TICKETS_ID_SEQ" minvalue 1  start with 1 increment by 1;

            create trigger "TICKETS_ID_TRG"
            before insert on "TICKETS"
            for each row
                begin
            if :new."ID" is null then
                select "TICKETS_ID_SEQ".nextval into :new."ID" from dual;
            end if;
            end;;

-- ---- 2026_06_18_100002_create_ticket_comments_table.php ----
create table "TICKET_COMMENTS" ( "ID" number(19,0) not null, "TICKET_ID" number(19,0) not null, "USER_ID" number(19,0) null, "COMMENT" clob not null, "ATTACHMENT_PATH" varchar2(255) null, "CREATED_AT" timestamp null, "UPDATED_AT" timestamp null, constraint tcm_ticket_fk foreign key ( "TICKET_ID" ) references "TICKETS" ( "ID" ) on delete cascade, constraint tcm_user_fk foreign key ( "USER_ID" ) references "USERS" ( "ID" ) on delete set null, constraint ticket_comments_id_pk primary key ( "ID" ) );
create index tcm_ticket_idx on "TICKET_COMMENTS" ( "TICKET_ID" );
create sequence "TICKET_COMMENTS_ID_SEQ" minvalue 1  start with 1 increment by 1;

            create trigger "TICKET_COMMENTS_ID_TRG"
            before insert on "TICKET_COMMENTS"
            for each row
                begin
            if :new."ID" is null then
                select "TICKET_COMMENTS_ID_SEQ".nextval into :new."ID" from dual;
            end if;
            end;;

-- ---- 2026_06_18_100003_create_strategic_goals_table.php ----
create table "STRATEGIC_GOALS" ( "ID" number(19,0) not null, "TITLE" varchar2(255) not null, "DESCRIPTION" clob null, "FISCAL_YEAR" varchar2(9) null, "OWNER_ID" number(19,0) null, "STATUS" varchar2(20) default 'active' not null, "START_DATE" date null, "END_DATE" date null, "CREATED_AT" timestamp null, "UPDATED_AT" timestamp null, constraint sgo_owner_fk foreign key ( "OWNER_ID" ) references "USERS" ( "ID" ) on delete set null, constraint strategic_goals_id_pk primary key ( "ID" ) );
create sequence "STRATEGIC_GOALS_ID_SEQ" minvalue 1  start with 1 increment by 1;

            create trigger "STRATEGIC_GOALS_ID_TRG"
            before insert on "STRATEGIC_GOALS"
            for each row
                begin
            if :new."ID" is null then
                select "STRATEGIC_GOALS_ID_SEQ".nextval into :new."ID" from dual;
            end if;
            end;;

-- ---- 2026_06_18_100004_create_strategic_plans_table.php ----
create table "STRATEGIC_PLANS" ( "ID" number(19,0) not null, "STRATEGIC_GOAL_ID" number(19,0) not null, "TITLE" varchar2(255) not null, "DESCRIPTION" clob null, "BUDGET" number(18, 2) default '0' not null, "START_DATE" date null, "END_DATE" date null, "STATUS" varchar2(20) default 'planned' not null, "CREATED_AT" timestamp null, "UPDATED_AT" timestamp null, constraint spl_goal_fk foreign key ( "STRATEGIC_GOAL_ID" ) references "STRATEGIC_GOALS" ( "ID" ) on delete cascade, constraint strategic_plans_id_pk primary key ( "ID" ) );
create index spl_goal_idx on "STRATEGIC_PLANS" ( "STRATEGIC_GOAL_ID" );
create sequence "STRATEGIC_PLANS_ID_SEQ" minvalue 1  start with 1 increment by 1;

            create trigger "STRATEGIC_PLANS_ID_TRG"
            before insert on "STRATEGIC_PLANS"
            for each row
                begin
            if :new."ID" is null then
                select "STRATEGIC_PLANS_ID_SEQ".nextval into :new."ID" from dual;
            end if;
            end;;

-- ---- 2026_06_18_100005_create_action_items_table.php ----
create table "ACTION_ITEMS" ( "ID" number(19,0) not null, "STRATEGIC_PLAN_ID" number(19,0) not null, "TITLE" varchar2(255) not null, "DESCRIPTION" clob null, "ASSIGNED_TO" number(19,0) null, "DUE_DATE" date null, "STATUS" varchar2(20) default 'todo' not null, "PROGRESS" number(3,0) default '0' not null, "CREATED_AT" timestamp null, "UPDATED_AT" timestamp null, constraint ait_plan_fk foreign key ( "STRATEGIC_PLAN_ID" ) references "STRATEGIC_PLANS" ( "ID" ) on delete cascade, constraint ait_assignee_fk foreign key ( "ASSIGNED_TO" ) references "USERS" ( "ID" ) on delete set null, constraint action_items_id_pk primary key ( "ID" ) );
create index ait_plan_idx on "ACTION_ITEMS" ( "STRATEGIC_PLAN_ID" );
create sequence "ACTION_ITEMS_ID_SEQ" minvalue 1  start with 1 increment by 1;

            create trigger "ACTION_ITEMS_ID_TRG"
            before insert on "ACTION_ITEMS"
            for each row
                begin
            if :new."ID" is null then
                select "ACTION_ITEMS_ID_SEQ".nextval into :new."ID" from dual;
            end if;
            end;;

-- ---- 2026_06_18_100006_create_review_sessions_table.php ----
create table "REVIEW_SESSIONS" ( "ID" number(19,0) not null, "TITLE" varchar2(255) not null, "SESSION_DATE" date not null, "CHAIRED_BY" number(19,0) null, "MINUTES" clob null, "DECISIONS" clob null, "STATUS" varchar2(20) default 'draft' not null, "CREATED_AT" timestamp null, "UPDATED_AT" timestamp null, constraint rvs_chair_fk foreign key ( "CHAIRED_BY" ) references "USERS" ( "ID" ) on delete set null, constraint review_sessions_id_pk primary key ( "ID" ) );
create sequence "REVIEW_SESSIONS_ID_SEQ" minvalue 1  start with 1 increment by 1;

            create trigger "REVIEW_SESSIONS_ID_TRG"
            before insert on "REVIEW_SESSIONS"
            for each row
                begin
            if :new."ID" is null then
                select "REVIEW_SESSIONS_ID_SEQ".nextval into :new."ID" from dual;
            end if;
            end;;

-- ---- 2026_06_18_100007_create_action_ticket_link_table.php ----
create table "ACTION_TICKET_LINK" ( "ID" number(19,0) not null, "ACTION_ITEM_ID" number(19,0) not null, "TICKET_ID" number(19,0) not null, "CREATED_AT" timestamp null, "UPDATED_AT" timestamp null, constraint atl_action_fk foreign key ( "ACTION_ITEM_ID" ) references "ACTION_ITEMS" ( "ID" ) on delete cascade, constraint atl_ticket_fk foreign key ( "TICKET_ID" ) references "TICKETS" ( "ID" ) on delete cascade, constraint action_ticket_link_id_pk primary key ( "ID" ) );
alter table "ACTION_TICKET_LINK" add constraint atl_action_ticket_uq unique ( "ACTION_ITEM_ID", "TICKET_ID" );
create sequence "ACTION_TICKET_LINK_ID_SEQ" minvalue 1  start with 1 increment by 1;

            create trigger "ACTION_TICKET_LINK_ID_TRG"
            before insert on "ACTION_TICKET_LINK"
            for each row
                begin
            if :new."ID" is null then
                select "ACTION_TICKET_LINK_ID_SEQ".nextval into :new."ID" from dual;
            end if;
            end;;

-- ---- 2026_06_18_100008_create_review_ticket_summaries_table.php ----
create table "REVIEW_TICKET_SUMMARIES" ( "ID" number(19,0) not null, "REVIEW_SESSION_ID" number(19,0) not null, "CATEGORY" varchar2(50) null, "PERIOD_START" date null, "PERIOD_END" date null, "TOTAL_TICKETS" number(10,0) default '0' not null, "RESOLVED_TICKETS" number(10,0) default '0' not null, "AVG_DURATION_HOURS" number(12, 2) default '0' not null, "TOTAL_COST" number(18, 2) default '0' not null, "NOTES" clob null, "CREATED_AT" timestamp null, "UPDATED_AT" timestamp null, constraint rts_session_fk foreign key ( "REVIEW_SESSION_ID" ) references "REVIEW_SESSIONS" ( "ID" ) on delete cascade, constraint review_ticket_summaries_id_pk primary key ( "ID" ) );
create index rts_session_idx on "REVIEW_TICKET_SUMMARIES" ( "REVIEW_SESSION_ID" );
create sequence "REVIEW_TICKET_SUMMARIES_ID_SEQ" minvalue 1  start with 1 increment by 1;

            create trigger "REVIEW_TICKET_SUMMARIES_ID_TRG"
            before insert on "REVIEW_TICKET_SUMMARIES"
            for each row
                begin
            if :new."ID" is null then
                select "REVIEW_TICKET_SUMMARIES_ID_SEQ".nextval into :new."ID" from dual;
            end if;
            end;;

-- ---- 2026_06_18_110001_create_ticket_categories_table.php ----
create table "TICKET_CATEGORIES" ( "ID" number(19,0) not null, "NAME" varchar2(100) not null, "CODE" varchar2(30) null, "DESCRIPTION" clob null, "IS_ACTIVE" char(1) default '1' not null, "CREATED_AT" timestamp null, "UPDATED_AT" timestamp null, constraint ticket_categories_id_pk primary key ( "ID" ) );
alter table "TICKET_CATEGORIES" add constraint tcat_name_uq unique ( "NAME" );
create sequence "TICKET_CATEGORIES_ID_SEQ" minvalue 1  start with 1 increment by 1;

            create trigger "TICKET_CATEGORIES_ID_TRG"
            before insert on "TICKET_CATEGORIES"
            for each row
                begin
            if :new."ID" is null then
                select "TICKET_CATEGORIES_ID_SEQ".nextval into :new."ID" from dual;
            end if;
            end;;

-- ---- 2026_06_18_110002_create_locations_table.php ----
create table "LOCATIONS" ( "ID" number(19,0) not null, "NAME" varchar2(100) not null, "CODE" varchar2(30) null, "BUILDING" varchar2(100) null, "FLOOR" varchar2(30) null, "DESCRIPTION" clob null, "IS_ACTIVE" char(1) default '1' not null, "CREATED_AT" timestamp null, "UPDATED_AT" timestamp null, constraint locations_id_pk primary key ( "ID" ) );
alter table "LOCATIONS" add constraint loc_name_uq unique ( "NAME" );
create sequence "LOCATIONS_ID_SEQ" minvalue 1  start with 1 increment by 1;

            create trigger "LOCATIONS_ID_TRG"
            before insert on "LOCATIONS"
            for each row
                begin
            if :new."ID" is null then
                select "LOCATIONS_ID_SEQ".nextval into :new."ID" from dual;
            end if;
            end;;

-- ---- 2026_06_18_110003_create_priorities_table.php ----
create table "PRIORITIES" ( "ID" number(19,0) not null, "NAME" varchar2(50) not null, "LEVEL" number(10,0) default '0' not null, "COLOR" varchar2(20) default '#6b7280' not null, "SLA_HOURS" number(10,0) null, "IS_ACTIVE" char(1) default '1' not null, "CREATED_AT" timestamp null, "UPDATED_AT" timestamp null, constraint priorities_id_pk primary key ( "ID" ) );
alter table "PRIORITIES" add constraint prio_name_uq unique ( "NAME" );
create sequence "PRIORITIES_ID_SEQ" minvalue 1  start with 1 increment by 1;

            create trigger "PRIORITIES_ID_TRG"
            before insert on "PRIORITIES"
            for each row
                begin
            if :new."ID" is null then
                select "PRIORITIES_ID_SEQ".nextval into :new."ID" from dual;
            end if;
            end;;

-- ---- 2026_06_18_110004_create_departments_table.php ----
create table "DEPARTMENTS" ( "ID" number(19,0) not null, "NAME" varchar2(100) not null, "CODE" varchar2(30) null, "HEAD_NAME" varchar2(100) null, "DESCRIPTION" clob null, "IS_ACTIVE" char(1) default '1' not null, "CREATED_AT" timestamp null, "UPDATED_AT" timestamp null, constraint departments_id_pk primary key ( "ID" ) );
alter table "DEPARTMENTS" add constraint dept_name_uq unique ( "NAME" );
create sequence "DEPARTMENTS_ID_SEQ" minvalue 1  start with 1 increment by 1;

            create trigger "DEPARTMENTS_ID_TRG"
            before insert on "DEPARTMENTS"
            for each row
                begin
            if :new."ID" is null then
                select "DEPARTMENTS_ID_SEQ".nextval into :new."ID" from dual;
            end if;
            end;;

-- ---- 2026_06_18_120000_alter_users_add_last_seen.php ----
ALTER TABLE users ADD (last_seen_at TIMESTAMP, last_seen_route VARCHAR2(255));

-- ---- 2026_06_19_100001_create_ticket_statuses_table.php ----
create table "TICKET_STATUSES" ( "ID" number(19,0) not null, "NAME" varchar2(50) not null, "CODE" varchar2(30) null, "COLOR" varchar2(20) default '#6b7280' not null, "SORT_ORDER" number(10,0) default '0' not null, "IS_CLOSED" char(1) default '0' not null, "IS_ACTIVE" char(1) default '1' not null, "CREATED_AT" timestamp null, "UPDATED_AT" timestamp null, constraint ticket_statuses_id_pk primary key ( "ID" ) );
alter table "TICKET_STATUSES" add constraint tst_name_uq unique ( "NAME" );
create sequence "TICKET_STATUSES_ID_SEQ" minvalue 1  start with 1 increment by 1;

            create trigger "TICKET_STATUSES_ID_TRG"
            before insert on "TICKET_STATUSES"
            for each row
                begin
            if :new."ID" is null then
                select "TICKET_STATUSES_ID_SEQ".nextval into :new."ID" from dual;
            end if;
            end;;

-- ---- 2026_06_19_100002_alter_tickets_use_master_fk.php ----
drop index tic_status_idx;
drop index tic_category_idx;
alter table "TICKETS" drop ( "CATEGORY", "PRIORITY", "STATUS", "LOCATION" );
alter table "TICKETS" add ( "CATEGORY_ID" number(19,0) null );
alter table "TICKETS" add ( "PRIORITY_ID" number(19,0) null );
alter table "TICKETS" add ( "STATUS_ID" number(19,0) null );
alter table "TICKETS" add ( "LOCATION_ID" number(19,0) null );
alter table "TICKETS" add ( "DEPARTMENT_ID" number(19,0) null );
create index tic_status_idx on "TICKETS" ( "STATUS_ID" );
create index tic_category_idx on "TICKETS" ( "CATEGORY_ID" );
alter table "TICKETS" add constraint tic_category_fk foreign key ( "CATEGORY_ID" ) references "TICKET_CATEGORIES" ( "ID" ) on delete set null;
alter table "TICKETS" add constraint tic_priority_fk foreign key ( "PRIORITY_ID" ) references "PRIORITIES" ( "ID" ) on delete set null;
alter table "TICKETS" add constraint tic_status_fk foreign key ( "STATUS_ID" ) references "TICKET_STATUSES" ( "ID" ) on delete set null;
alter table "TICKETS" add constraint tic_location_fk foreign key ( "LOCATION_ID" ) references "LOCATIONS" ( "ID" ) on delete set null;
alter table "TICKETS" add constraint tic_department_fk foreign key ( "DEPARTMENT_ID" ) references "DEPARTMENTS" ( "ID" ) on delete set null;

-- ---- 2026_06_19_100003_create_ticket_activities_table.php ----
create table "TICKET_ACTIVITIES" ( "ID" number(19,0) not null, "TICKET_ID" number(19,0) not null, "USER_ID" number(19,0) null, "ACTION" varchar2(30) not null, "FROM_VALUE" varchar2(100) null, "TO_VALUE" varchar2(100) null, "NOTE" clob null, "CREATED_AT" timestamp null, constraint tac_ticket_fk foreign key ( "TICKET_ID" ) references "TICKETS" ( "ID" ) on delete cascade, constraint tac_user_fk foreign key ( "USER_ID" ) references "USERS" ( "ID" ) on delete set null, constraint ticket_activities_id_pk primary key ( "ID" ) );
create index tac_ticket_idx on "TICKET_ACTIVITIES" ( "TICKET_ID" );
create sequence "TICKET_ACTIVITIES_ID_SEQ" minvalue 1  start with 1 increment by 1;

            create trigger "TICKET_ACTIVITIES_ID_TRG"
            before insert on "TICKET_ACTIVITIES"
            for each row
                begin
            if :new."ID" is null then
                select "TICKET_ACTIVITIES_ID_SEQ".nextval into :new."ID" from dual;
            end if;
            end;;

-- ---- 2026_06_19_100004_alter_tickets_add_first_response.php ----
alter table "TICKETS" add ( "FIRST_RESPONDED_BY" number(19,0) null );
alter table "TICKETS" add ( "FIRST_RESPONDED_AT" timestamp null );
alter table "TICKETS" add constraint tic_firstresp_fk foreign key ( "FIRST_RESPONDED_BY" ) references "USERS" ( "ID" ) on delete set null;

-- ---- 2026_07_05_100001_alter_departments_add_is_sector.php ----
alter table "DEPARTMENTS" add ( "IS_SECTOR" char(1) default '0' not null );

-- ---- 2026_07_05_100002_alter_ticket_categories_add_sector.php ----
alter table "TICKET_CATEGORIES" add ( "SECTOR_ID" number(19,0) null );
alter table "TICKET_CATEGORIES" add constraint tcat_sector_fk foreign key ( "SECTOR_ID" ) references "DEPARTMENTS" ( "ID" ) on delete set null;

-- ---- 2026_07_05_100003_alter_users_add_sector.php ----
alter table "USERS" add ( "SECTOR_ID" number(19,0) null );
alter table "USERS" add constraint usr_sector_fk foreign key ( "SECTOR_ID" ) references "DEPARTMENTS" ( "ID" ) on delete set null;

-- ---- 2026_07_05_100004_alter_tickets_add_sector.php ----
alter table "TICKETS" add ( "SECTOR_ID" number(19,0) null );
create index tic_sector_idx on "TICKETS" ( "SECTOR_ID" );
alter table "TICKETS" add constraint tic_sector_fk foreign key ( "SECTOR_ID" ) references "DEPARTMENTS" ( "ID" ) on delete set null;

-- ---- 2026_07_05_100005_alter_users_drop_myuser_profesi.php ----
ALTER TABLE users DROP COLUMN myuser_profesi;

-- ---- 2026_07_05_100006_alter_tickets_add_location_detail.php ----
alter table "TICKETS" add ( "LOCATION_DETAIL" varchar2(500) null );

-- ---- 2026_07_05_100007_alter_tickets_add_reporter_name.php ----
alter table "TICKETS" add ( "REPORTER_NAME" varchar2(150) null );

-- ---- 2026_07_05_100008_create_patient_reviews_table.php ----
create table "PATIENT_REVIEWS" ( "ID" number(19,0) not null, "REVIEWER_NAME" varchar2(150) not null, "RATING" number(3,0) not null, "COMMENT" clob null, "IS_VISIBLE" char(1) default '1' not null, "CREATED_AT" timestamp null, "UPDATED_AT" timestamp null, constraint patient_reviews_id_pk primary key ( "ID" ) );
create index prev_visible_idx on "PATIENT_REVIEWS" ( "IS_VISIBLE" );
create sequence "PATIENT_REVIEWS_ID_SEQ" minvalue 1  start with 1 increment by 1;

            create trigger "PATIENT_REVIEWS_ID_TRG"
            before insert on "PATIENT_REVIEWS"
            for each row
                begin
            if :new."ID" is null then
                select "PATIENT_REVIEWS_ID_SEQ".nextval into :new."ID" from dual;
            end if;
            end;;

-- ---- 2026_07_05_100009_alter_patient_reviews_add_photo.php ----
alter table "PATIENT_REVIEWS" add ( "PHOTO_PATH" varchar2(500) null );

-- ---- 2026_07_05_100010_create_survey_tables.php ----
create table "SURVEYS" ( "ID" number(19,0) not null, "TITLE" varchar2(200) not null, "DESCRIPTION" clob null, "STATUS" varchar2(20) default 'draft' not null, "OPENED_AT" timestamp null, "CLOSED_AT" timestamp null, "CREATED_BY" number(19,0) null, "CREATED_AT" timestamp null, "UPDATED_AT" timestamp null, constraint srv_creator_fk foreign key ( "CREATED_BY" ) references "USERS" ( "ID" ) on delete set null, constraint surveys_id_pk primary key ( "ID" ) );
create index srv_status_idx on "SURVEYS" ( "STATUS" );
create sequence "SURVEYS_ID_SEQ" minvalue 1  start with 1 increment by 1;

            create trigger "SURVEYS_ID_TRG"
            before insert on "SURVEYS"
            for each row
                begin
            if :new."ID" is null then
                select "SURVEYS_ID_SEQ".nextval into :new."ID" from dual;
            end if;
            end;;
create table "SURVEY_QUESTIONS" ( "ID" number(19,0) not null, "SURVEY_ID" number(19,0) not null, "QUESTION" varchar2(500) not null, "TYPE" varchar2(20) default 'rating' not null, "SORT_ORDER" number(10,0) default '0' not null, "CREATED_AT" timestamp null, "UPDATED_AT" timestamp null, constraint sq_survey_fk foreign key ( "SURVEY_ID" ) references "SURVEYS" ( "ID" ) on delete cascade, constraint survey_questions_id_pk primary key ( "ID" ) );
create index sq_survey_idx on "SURVEY_QUESTIONS" ( "SURVEY_ID" );
create sequence "SURVEY_QUESTIONS_ID_SEQ" minvalue 1  start with 1 increment by 1;

            create trigger "SURVEY_QUESTIONS_ID_TRG"
            before insert on "SURVEY_QUESTIONS"
            for each row
                begin
            if :new."ID" is null then
                select "SURVEY_QUESTIONS_ID_SEQ".nextval into :new."ID" from dual;
            end if;
            end;;
create table "SURVEY_RESPONSES" ( "ID" number(19,0) not null, "SURVEY_ID" number(19,0) not null, "RESPONDENT_NAME" varchar2(150) null, "CREATED_AT" timestamp null, "UPDATED_AT" timestamp null, constraint sr_survey_fk foreign key ( "SURVEY_ID" ) references "SURVEYS" ( "ID" ) on delete cascade, constraint survey_responses_id_pk primary key ( "ID" ) );
create index sr_survey_idx on "SURVEY_RESPONSES" ( "SURVEY_ID" );
create sequence "SURVEY_RESPONSES_ID_SEQ" minvalue 1  start with 1 increment by 1;

            create trigger "SURVEY_RESPONSES_ID_TRG"
            before insert on "SURVEY_RESPONSES"
            for each row
                begin
            if :new."ID" is null then
                select "SURVEY_RESPONSES_ID_SEQ".nextval into :new."ID" from dual;
            end if;
            end;;
create table "SURVEY_ANSWERS" ( "ID" number(19,0) not null, "RESPONSE_ID" number(19,0) not null, "QUESTION_ID" number(19,0) not null, "VALUE" clob null, "CREATED_AT" timestamp null, "UPDATED_AT" timestamp null, constraint sa_response_fk foreign key ( "RESPONSE_ID" ) references "SURVEY_RESPONSES" ( "ID" ) on delete cascade, constraint sa_question_fk foreign key ( "QUESTION_ID" ) references "SURVEY_QUESTIONS" ( "ID" ) on delete cascade, constraint survey_answers_id_pk primary key ( "ID" ) );
create index sa_question_idx on "SURVEY_ANSWERS" ( "QUESTION_ID" );
create sequence "SURVEY_ANSWERS_ID_SEQ" minvalue 1  start with 1 increment by 1;

            create trigger "SURVEY_ANSWERS_ID_TRG"
            before insert on "SURVEY_ANSWERS"
            for each row
                begin
            if :new."ID" is null then
                select "SURVEY_ANSWERS_ID_SEQ".nextval into :new."ID" from dual;
            end if;
            end;;

-- ---- 2026_07_05_100011_alter_survey_questions_add_options.php ----
alter table "SURVEY_QUESTIONS" add ( "OPTIONS" clob null );

-- ---- 2026_07_05_100012_create_incident_reports_table.php ----
create table "INCIDENT_REPORTS" ( "ID" number(19,0) not null, "REPORT_NO" varchar2(30) not null, "CATEGORY" varchar2(50) not null, "TITLE" varchar2(255) not null, "DESCRIPTION" clob not null, "LOCATION" varchar2(255) null, "INCIDENT_DATE" date null, "REPORTER_NAME" varchar2(150) null, "REPORTER_CONTACT" varchar2(150) null, "IS_ANONYMOUS" char(1) default '0' not null, "STATUS" varchar2(30) default 'baru' not null, "SOURCE" varchar2(20) default 'web' not null, "CREATED_AT" timestamp null, "UPDATED_AT" timestamp null, constraint incident_reports_id_pk primary key ( "ID" ) );
create index increp_status_idx on "INCIDENT_REPORTS" ( "STATUS" );
alter table "INCIDENT_REPORTS" add constraint increp_no_uq unique ( "REPORT_NO" );
create sequence "INCIDENT_REPORTS_ID_SEQ" minvalue 1  start with 1 increment by 1;

            create trigger "INCIDENT_REPORTS_ID_TRG"
            before insert on "INCIDENT_REPORTS"
            for each row
                begin
            if :new."ID" is null then
                select "INCIDENT_REPORTS_ID_SEQ".nextval into :new."ID" from dual;
            end if;
            end;;

-- ---- 2026_07_18_100001_create_patient_safety_incidents_table.php ----
create table "PATIENT_SAFETY_INCIDENTS" ( "ID" number(19,0) not null, "REPORT_NO" varchar2(30) not null, "INCIDENT_TYPE" varchar2(20) not null, "TITLE" varchar2(255) not null, "CHRONOLOGY" clob not null, "PATIENT_IMPACT" clob null, "IMMEDIATE_ACTION" clob null, "GRADING" varchar2(10) null, "LOCATION" varchar2(255) null, "INCIDENT_DATE" date null, "INCIDENT_TIME" varchar2(5) null, "PATIENT_REF" varchar2(100) null, "REPORTER_NAME" varchar2(150) null, "REPORTER_ROLE" varchar2(100) null, "REPORTER_CONTACT" varchar2(150) null, "IS_ANONYMOUS" char(1) default '0' not null, "STATUS" varchar2(30) default 'baru' not null, "SOURCE" varchar2(20) default 'web' not null, "CREATED_AT" timestamp null, "UPDATED_AT" timestamp null, constraint patient_safety_incidents_id_pk primary key ( "ID" ) );
create index psi_status_idx on "PATIENT_SAFETY_INCIDENTS" ( "STATUS" );
create index psi_type_idx on "PATIENT_SAFETY_INCIDENTS" ( "INCIDENT_TYPE" );
alter table "PATIENT_SAFETY_INCIDENTS" add constraint psi_no_uq unique ( "REPORT_NO" );
create sequence "PATIENT_SAFETY_INCIDENTS_ID_SE" minvalue 1  start with 1 increment by 1;

            create trigger "PATIENT_SAFETY_INCIDENTS_ID_TR"
            before insert on "PATIENT_SAFETY_INCIDENTS"
            for each row
                begin
            if :new."ID" is null then
                select "PATIENT_SAFETY_INCIDENTS_ID_SE".nextval into :new."ID" from dual;
            end if;
            end;;

