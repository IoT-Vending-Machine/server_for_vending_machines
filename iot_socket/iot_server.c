/* 서울기술교육센터 AIoT
 * iot_server.c - original Makefile compatible version
 * - No #include <mysql/mysql.h>
 * - Uses mariadb/mysql CLI already installed on Raspberry Pi
 * - Keeps original socket relay behavior
 * - Adds DB handling for VEND, SENSOR, RESTOCK/RESET
 * - Blue button RESTOCK refills every machine/product to 10, including VM_02/busan
 */
#include <stdio.h>
#include <stdlib.h>
#include <unistd.h>
#include <string.h>
#include <strings.h>
#include <arpa/inet.h>
#include <sys/types.h>
#include <sys/socket.h>
#include <netinet/in.h>
#include <pthread.h>
#include <sys/time.h>
#include <sys/wait.h>
#include <time.h>
#include <errno.h>
#include <stdbool.h>

#define DB_HOST "localhost"
#define DB_USER "iot"
#define DB_PASS "pwiot"
#define DB_NAME "iotdb"

#define LOCAL_MACHINE_ID 1       /* STM32/KJM_BLT가 실제로 붙어 있는 자판기 */
#define BUSAN_MACHINE_ID 2       /* VM_02 / busan */
#define MAX_STOCK 10
#define PRODUCT_COUNT 3

#define ID_ARD   "KJM_ARD"       /* Arduino, 기존 seoul 자판기 ID */
#define ID_BLT   "KJM_BLT"       /* Raspberry Pi bluetooth bridge, STM32 연결 */
#define ID_SQL   "KJM_SQL"       /* DB 처리 주체 이름으로 응답 prefix에 사용 */
#define ID_LIN   "KJM_LIN"
#define ID_VM01  "VM_01"
#define ID_VM02  "VM_02"
#define ID_STM32 "STM32"

#define BUF_SIZE 512
#define MAX_CLNT 34
#define ID_SIZE 10
#define ARR_CNT 8

typedef struct {
        int fd;
        char *from;
        char *to;
        char *msg;
        int len;
} MSG_INFO;

typedef struct {
        int index;
        int fd;
        char ip[20];
        char id[ID_SIZE];
        char pw[ID_SIZE];
} CLIENT_INFO;

typedef struct {
        int local_missing;
        int all_missing;
} MISSING_INFO;

void * clnt_connection(void * arg);
void send_msg(MSG_INFO * msg_info, CLIENT_INFO * first_client_info);
void error_handling(char * msg);
void log_file(char * msgstr);
void getlocaltime(char * buf);

static void trim_newline(char *s);
static void sql_escape(const char *src, char *dst, size_t dstsz);
static const char *db_cli_name(void);
static int db_write_temp_sql(const char *sql, char *path, size_t pathsz);
static int db_exec_sql(const char *sql);
static int db_query_lines(const char *sql, void (*cb)(const char *line, void *arg), void *arg);
static void db_init_schema(void);
static int machine_id_from_client_location(const char *client_id, const char *location);
static void db_sync_stock_fd(int client_fd, const char *client_id);
static void db_vend(const char *from, int product_id, int remain, const char *location);
static void db_sensor(const char *from, float temp, float humi, int cds);
static int db_get_missing(MISSING_INFO *out);
static void db_restock_all(const char *reason, bool has_sensor, float temp, float humi, MISSING_INFO before);
static void route_to_id(CLIENT_INFO *first_client_info, const char *to, const char *from, const char *payload);
static void notify_stock_update(CLIENT_INFO *first_client_info, int machine_id, int product_id, int stock);
static void notify_all_products_full(CLIENT_INFO *first_client_info);
static int handle_server_command(const char *from, const char *to, const char *payload, CLIENT_INFO *first_client_info);

int clnt_cnt=0;
pthread_mutex_t mutx;

int main(int argc, char *argv[])
{
        int serv_sock, clnt_sock;
        struct sockaddr_in serv_adr, clnt_adr;
        socklen_t clnt_adr_sz;
        int sock_option  = 1;
        pthread_t t_id[MAX_CLNT] = {0};
        int str_len = 0;
        int i=0;
        char idpasswd[(ID_SIZE*2)+3];
        char *pToken;
        char *pArray[ARR_CNT]={0};
        char msg[BUF_SIZE];

        FILE * idFd = fopen("idpasswd.txt","r");
        if(idFd == NULL)
        {
            perror("fopen(\"idpasswd.txt\",\"r\") ");
            exit(1);
        }

        char id[ID_SIZE];
        char pw[ID_SIZE];
        CLIENT_INFO * client_info = (CLIENT_INFO *)calloc(MAX_CLNT, sizeof(CLIENT_INFO));
        if(client_info == NULL)
        {
            perror("calloc()");
            exit(1);
        }

        do {
            str_len = fscanf(idFd,"%9s %9s",id,pw);
            if(str_len <= 0)
                break;
            if(i >= MAX_CLNT)
            {
                printf("error client_info pool(Max:%d)\n",MAX_CLNT);
                break;
            }
            client_info[i].fd=-1;
            snprintf(client_info[i].id, sizeof(client_info[i].id), "%s", id);
            snprintf(client_info[i].pw, sizeof(client_info[i].pw), "%s", pw);
            i++;
        } while(1);
        fclose(idFd);

        if(argc != 2) {
                printf("Usage : %s <port>\n",argv[0]);
                exit(1);
        }
        fputs("IoT Server Start!!\n",stdout);
        printf("DB mode: no mysql/mysql.h, use CLI command: %s\n", db_cli_name());

        if(pthread_mutex_init(&mutx, NULL))
                error_handling("mutex init error");

        db_init_schema();

        serv_sock = socket(PF_INET, SOCK_STREAM, 0);
        if(serv_sock == -1)
                error_handling("socket() error");

        memset(&serv_adr, 0, sizeof(serv_adr));
        serv_adr.sin_family=AF_INET;
        serv_adr.sin_addr.s_addr=htonl(INADDR_ANY);
        serv_adr.sin_port=htons((uint16_t)atoi(argv[1]));

        setsockopt(serv_sock, SOL_SOCKET, SO_REUSEADDR, (void*)&sock_option, sizeof(sock_option));
        if(bind(serv_sock, (struct sockaddr *)&serv_adr, sizeof(serv_adr))==-1)
                error_handling("bind() error");

        if(listen(serv_sock, 5) == -1)
                error_handling("listen() error");

        while(1) {
                clnt_adr_sz = sizeof(clnt_adr);
                clnt_sock = accept(serv_sock, (struct sockaddr *)&clnt_adr, &clnt_adr_sz);
                if(clnt_cnt >= MAX_CLNT)
                {
                        printf("socket full\n");
                        shutdown(clnt_sock,SHUT_WR);
                        close(clnt_sock);
                        continue;
                }
                else if(clnt_sock < 0)
                {
                        perror("accept()");
                        continue;
                }

                memset(idpasswd, 0, sizeof(idpasswd));
                str_len = read(clnt_sock, idpasswd, sizeof(idpasswd)-1);
                if(str_len > 0)
                {
                        idpasswd[str_len] = '\0';
                        i=0;
                        memset(pArray, 0, sizeof(pArray));
                        pToken = strtok(idpasswd,"[:]");

                        while(pToken != NULL)
                        {
                                pArray[i] =  pToken;
                                if(++i >= ARR_CNT)
                                        break;
                                pToken = strtok(NULL,"[:]");
                        }

                        if(pArray[0] == NULL || pArray[1] == NULL)
                        {
                                shutdown(clnt_sock,SHUT_WR);
                                close(clnt_sock);
                                continue;
                        }

                        for(i=0;i<MAX_CLNT;i++)
                        {
                                if(!strcmp(client_info[i].id,pArray[0]))
                                {
                                        if(client_info[i].fd != -1)
                                        {
                                                sprintf(msg,"[%s] Already logged! old connection will be replaced\n",pArray[0]);
                                                write(client_info[i].fd, msg,strlen(msg));
                                                close(client_info[i].fd);
                                                pthread_mutex_lock(&mutx);
                                                client_info[i].fd = -1;
                                                if(clnt_cnt > 0) clnt_cnt--;
                                                pthread_mutex_unlock(&mutx);
                                        }
                                        if(!strcmp(client_info[i].pw,pArray[1]))
                                        {
                                                strncpy(client_info[i].ip,inet_ntoa(clnt_adr.sin_addr),sizeof(client_info[i].ip)-1);
                                                pthread_mutex_lock(&mutx);
                                                client_info[i].index = i;
                                                client_info[i].fd = clnt_sock;
                                                clnt_cnt++;
                                                pthread_mutex_unlock(&mutx);
                                                sprintf(msg,"[%s] New connected! (ip:%s,fd:%d,sockcnt:%d)\n",pArray[0],inet_ntoa(clnt_adr.sin_addr),clnt_sock,clnt_cnt);
                                                log_file(msg);
                                                write(clnt_sock, msg,strlen(msg));

                                                if(!strcmp(pArray[0], ID_ARD) || !strcmp(pArray[0], ID_BLT) ||
                                                   !strcmp(pArray[0], ID_VM01) || !strcmp(pArray[0], ID_VM02) ||
                                                   !strcmp(pArray[0], ID_STM32))
                                                        db_sync_stock_fd(clnt_sock, pArray[0]);

                                                pthread_create(t_id+i, NULL, clnt_connection, (void *)(client_info + i));
                                                pthread_detach(t_id[i]);
                                                break;
                                        }
                                }
                        }
                        if(i == MAX_CLNT)
                        {
                                sprintf(msg,"[%s] Authentication Error!\n",pArray[0]);
                                write(clnt_sock, msg,strlen(msg));
                                log_file(msg);
                                shutdown(clnt_sock,SHUT_WR);
                                close(clnt_sock);
                        }
                }
                else {
                        shutdown(clnt_sock,SHUT_WR);
                        close(clnt_sock);
                }
        }
        return 0;
}

void * clnt_connection(void *arg)
{
        CLIENT_INFO * client_info = (CLIENT_INFO *)arg;
        int str_len = 0;
        int index = client_info->index;
        char msg[BUF_SIZE];
        char to_msg[MAX_CLNT*ID_SIZE+BUF_SIZE];
        int i=0;
        char *pToken;
        char *pArray[ARR_CNT]={0};
        char strBuff[BUF_SIZE*2]={0};

        MSG_INFO msg_info;
        CLIENT_INFO  * first_client_info;

        first_client_info = (CLIENT_INFO *)((char *)client_info - (sizeof(CLIENT_INFO) * index));
        while(1)
        {
                memset(msg,0x0,sizeof(msg));
                str_len = read(client_info->fd, msg, sizeof(msg)-1);
                if(str_len <= 0)
                        break;

                msg[str_len] = '\0';
                trim_newline(msg);
                if(msg[0] == '\0')
                        continue;

                memset(pArray, 0, sizeof(pArray));
                pToken = strtok(msg,"[:]");
                i = 0;
                while(pToken != NULL)
                {
                        pArray[i] =  pToken;
                        if(++i >= ARR_CNT)
                                break;
                        pToken = strtok(NULL,"[:]");
                }

                if(pArray[0] == NULL || pArray[1] == NULL)
                        continue;

                msg_info.fd = client_info->fd;
                msg_info.from = client_info->id;
                msg_info.to = pArray[0];
                snprintf(to_msg, sizeof(to_msg), "[%s]%s\n", msg_info.from, pArray[1]);
                msg_info.msg = to_msg;
                msg_info.len = (int)strlen(to_msg);

                snprintf(strBuff,sizeof(strBuff),"msg : [%s->%s] %s\n",msg_info.from,msg_info.to,pArray[1]);
                log_file(strBuff);

                if(handle_server_command(msg_info.from, msg_info.to, pArray[1], first_client_info))
                        continue;

                send_msg(&msg_info, first_client_info);
        }

        close(client_info->fd);

        snprintf(strBuff,sizeof(strBuff),"Disconnect ID:%s (ip:%s,fd:%d,sockcnt:%d)\n",client_info->id,client_info->ip,client_info->fd,clnt_cnt-1);
        log_file(strBuff);

        pthread_mutex_lock(&mutx);
        if(clnt_cnt > 0) clnt_cnt--;
        client_info->fd = -1;
        pthread_mutex_unlock(&mutx);

        return 0;
}

void send_msg(MSG_INFO * msg_info, CLIENT_INFO * first_client_info)
{
        int i=0;

        if(!strcmp(msg_info->to,"ALLMSG"))
        {
                for(i=0;i<MAX_CLNT;i++)
                        if((first_client_info+i)->fd != -1)
                                write((first_client_info+i)->fd, msg_info->msg, msg_info->len);
        }
        else if(!strcmp(msg_info->to,"IDLIST"))
        {
                char* idlist = (char *)malloc(ID_SIZE * MAX_CLNT + BUF_SIZE);
                if(idlist == NULL) return;
                strcpy(idlist,msg_info->msg);

                for(i=0;i<MAX_CLNT;i++)
                {
                        if((first_client_info+i)->fd != -1)
                        {
                                strcat(idlist,(first_client_info+i)->id);
                                strcat(idlist," ");
                        }
                }
                strcat(idlist,"\n");
                write(msg_info->fd, idlist, strlen(idlist));
                free(idlist);
        }
        else if(!strcmp(msg_info->to,"GETTIME"))
        {
            getlocaltime(msg_info->msg);
            write(msg_info->fd, msg_info->msg, strlen(msg_info->msg));
        }
        else
                for(i=0;i<MAX_CLNT;i++)
                        if((first_client_info+i)->fd != -1)
                                if(!strcmp(msg_info->to,(first_client_info+i)->id))
                                        write((first_client_info+i)->fd, msg_info->msg, msg_info->len);
}

static int handle_server_command(const char *from, const char *to, const char *payload, CLIENT_INFO *first_client_info)
{
        char buf[BUF_SIZE];
        char *cmd;
        char *p1;
        char *p2;
        char *p3;
        char *p4;

        (void)to;  /* VEND/RESTOCK/SENSOR는 목적지와 무관하게 서버가 먼저 처리 */
        if(payload == NULL) return 0;
        strncpy(buf, payload, sizeof(buf)-1);
        buf[sizeof(buf)-1] = '\0';
        trim_newline(buf);

        cmd = strtok(buf, "@:");
        if(cmd == NULL) return 0;

        if(!strcmp(cmd, "VEND"))
        {
                p1 = strtok(NULL, "@:");
                p2 = strtok(NULL, "@:");
                p3 = strtok(NULL, "@:");
                if(p1 == NULL || p2 == NULL) return 1;
                int product_id = atoi(p1);
                int remain = atoi(p2);
                const char *location = (p3 != NULL) ? p3 : "unknown";
                int machine_id = machine_id_from_client_location(from, location);

                db_vend(from, product_id, remain, location);
                notify_stock_update(first_client_info, machine_id, product_id, remain);
                route_to_id(first_client_info, from, ID_SQL, "VEND_OK");
                return 1;
        }
        else if(!strcmp(cmd, "SENSOR"))
        {
                /* Arduino format: SENSOR@temp@humi@cds */
                p1 = strtok(NULL, "@:");
                p2 = strtok(NULL, "@:");
                p3 = strtok(NULL, "@:");
                if(p1 == NULL || p2 == NULL || p3 == NULL) return 1;
                db_sensor(from, (float)atof(p1), (float)atof(p2), atoi(p3));
                return 1;
        }
        else if(!strcmp(cmd, "RESTOCK"))
        {
                /* New recommended STM32 format: RESTOCK@BLUE@temp@humi
                 * Old compatible format:        RESTOCK@BLUE@filled@temp@humi
                 */
                p1 = strtok(NULL, "@:");
                p2 = strtok(NULL, "@:");
                p3 = strtok(NULL, "@:");
                p4 = strtok(NULL, "@:");
                const char *reason = (p1 != NULL) ? p1 : "UNKNOWN";
                bool has_sensor = false;
                float temp = 0.0f;
                float humi = 0.0f;

                if(p2 != NULL && p3 != NULL && p4 == NULL) {
                        has_sensor = true;
                        temp = (float)atof(p2);
                        humi = (float)atof(p3);
                } else if(p3 != NULL && p4 != NULL) {
                        has_sensor = true;
                        temp = (float)atof(p3);
                        humi = (float)atof(p4);
                }

                MISSING_INFO before = {0, 0};
                db_get_missing(&before);
                db_restock_all(reason, has_sensor, temp, humi, before);
                notify_all_products_full(first_client_info);

                char payload_run[64];
                snprintf(payload_run, sizeof(payload_run), "RESTOCK_RUN@%d@%d", before.local_missing, before.all_missing);
                route_to_id(first_client_info, ID_BLT, ID_SQL, payload_run);
                route_to_id(first_client_info, ID_STM32, ID_SQL, payload_run);

                char payload_ok[96];
                snprintf(payload_ok, sizeof(payload_ok), "RESTOCK_OK@LOCAL@%d@ALL@%d", before.local_missing, before.all_missing);
                route_to_id(first_client_info, from, ID_SQL, payload_ok);
                route_to_id(first_client_info, ID_LIN, ID_SQL, payload_ok);
                return 1;
        }
        else if(!strcmp(cmd, "RESET"))
        {
                /* 기존 STM32 코드의 [KJM_SQL]RESET 호환: sales_log 삭제 없이 전체 재고만 10으로 복구 */
                MISSING_INFO before = {0, 0};
                db_get_missing(&before);
                db_restock_all("RESET", false, 0.0f, 0.0f, before);
                notify_all_products_full(first_client_info);

                char payload_run[64];
                snprintf(payload_run, sizeof(payload_run), "RESTOCK_RUN@%d@%d", before.local_missing, before.all_missing);
                route_to_id(first_client_info, ID_BLT, ID_SQL, payload_run);
                route_to_id(first_client_info, ID_STM32, ID_SQL, payload_run);

                char payload_ok[96];
                snprintf(payload_ok, sizeof(payload_ok), "RESET_OK@LOCAL@%d@ALL@%d", before.local_missing, before.all_missing);
                route_to_id(first_client_info, from, ID_SQL, payload_ok);
                route_to_id(first_client_info, ID_LIN, ID_SQL, payload_ok);
                return 1;
        }

        return 0;
}

static void route_to_id(CLIENT_INFO *first_client_info, const char *to, const char *from, const char *payload)
{
        char out[BUF_SIZE];
        int i;
        if(to == NULL || from == NULL || payload == NULL) return;
        snprintf(out, sizeof(out), "[%s]%s\n", from, payload);

        for(i=0; i<MAX_CLNT; i++)
        {
                if((first_client_info+i)->fd != -1 && !strcmp((first_client_info+i)->id, to))
                {
                        write((first_client_info+i)->fd, out, strlen(out));
                        return;
                }
        }
}

static void notify_stock_update(CLIENT_INFO *first_client_info, int machine_id, int product_id, int stock)
{
        char payload[64];
        snprintf(payload, sizeof(payload), "SETSTOCK@%d@%d", product_id, stock);

        if(machine_id == BUSAN_MACHINE_ID) {
                route_to_id(first_client_info, ID_VM02, ID_SQL, payload);
        } else {
                route_to_id(first_client_info, ID_ARD, ID_SQL, payload);
                route_to_id(first_client_info, ID_VM01, ID_SQL, payload);
                route_to_id(first_client_info, ID_BLT, ID_SQL, payload);
                route_to_id(first_client_info, ID_STM32, ID_SQL, payload);
        }
}

static void notify_all_products_full(CLIENT_INFO *first_client_info)
{
        int pid;
        char payload[64];
        const char *ids[] = { ID_ARD, ID_VM01, ID_VM02, ID_BLT, ID_STM32 };
        size_t id_count = sizeof(ids) / sizeof(ids[0]);

        for(pid=1; pid<=PRODUCT_COUNT; pid++) {
                snprintf(payload, sizeof(payload), "SETSTOCK@%d@%d", pid, MAX_STOCK);
                for(size_t i=0; i<id_count; i++)
                        route_to_id(first_client_info, ids[i], ID_SQL, payload);
        }
}

static void trim_newline(char *s)
{
        if(s == NULL) return;
        s[strcspn(s, "\r\n")] = '\0';
}

static void sql_escape(const char *src, char *dst, size_t dstsz)
{
        size_t j = 0;
        if(dstsz == 0) return;
        if(src == NULL) src = "";

        for(size_t i=0; src[i] != '\0' && j + 1 < dstsz; i++)
        {
                unsigned char c = (unsigned char)src[i];
                if(c == '\'' && j + 2 < dstsz)
                {
                        dst[j++] = '\'';
                        dst[j++] = '\'';
                }
                else if(c == '\\' && j + 2 < dstsz)
                {
                        dst[j++] = '\\';
                        dst[j++] = '\\';
                }
                else if(c == '\r' || c == '\n')
                {
                        dst[j++] = ' ';
                }
                else
                {
                        dst[j++] = (char)c;
                }
        }
        dst[j] = '\0';
}

static const char *db_cli_name(void)
{
        if(access("/usr/bin/mariadb", X_OK) == 0) return "mariadb";
        if(access("/bin/mariadb", X_OK) == 0) return "mariadb";
        if(access("/usr/bin/mysql", X_OK) == 0) return "mysql";
        if(access("/bin/mysql", X_OK) == 0) return "mysql";
        return "mysql";
}

static int db_write_temp_sql(const char *sql, char *path, size_t pathsz)
{
        char tmpl[] = "/tmp/iotdb_sql_XXXXXX";
        int fd = mkstemp(tmpl);
        if(fd < 0)
        {
                perror("mkstemp");
                return -1;
        }

        FILE *fp = fdopen(fd, "w");
        if(fp == NULL)
        {
                perror("fdopen");
                close(fd);
                unlink(tmpl);
                return -1;
        }

        fputs("SET NAMES utf8mb4;\n", fp);
        fputs(sql, fp);
        fputc('\n', fp);
        fclose(fp);

        strncpy(path, tmpl, pathsz-1);
        path[pathsz-1] = '\0';
        return 0;
}

static int db_exec_sql(const char *sql)
{
        char sqlfile[64];
        char cmd[768];
        int ret;

        if(db_write_temp_sql(sql, sqlfile, sizeof(sqlfile)) != 0)
                return -1;

        snprintf(cmd, sizeof(cmd), "%s --default-character-set=utf8mb4 -h%s -u%s -p%s %s < %s",
                 db_cli_name(), DB_HOST, DB_USER, DB_PASS, DB_NAME, sqlfile);

        ret = system(cmd);
        unlink(sqlfile);

        if(ret == -1)
        {
                perror("system(mysql)");
                return -1;
        }
        if(!WIFEXITED(ret) || WEXITSTATUS(ret) != 0)
        {
                fprintf(stderr, "DB command failed: %s\n", cmd);
                return -1;
        }
        return 0;
}

static int db_query_lines(const char *sql, void (*cb)(const char *line, void *arg), void *arg)
{
        char sqlfile[64];
        char cmd[768];
        char line[512];
        FILE *fp;
        int status;

        if(db_write_temp_sql(sql, sqlfile, sizeof(sqlfile)) != 0)
                return -1;

        snprintf(cmd, sizeof(cmd), "%s -N -B -r --default-character-set=utf8mb4 -h%s -u%s -p%s %s < %s",
                 db_cli_name(), DB_HOST, DB_USER, DB_PASS, DB_NAME, sqlfile);

        fp = popen(cmd, "r");
        if(fp == NULL)
        {
                perror("popen(mysql)");
                unlink(sqlfile);
                return -1;
        }

        while(fgets(line, sizeof(line), fp) != NULL)
        {
                trim_newline(line);
                if(cb != NULL && line[0] != '\0') cb(line, arg);
        }

        status = pclose(fp);
        unlink(sqlfile);

        if(status == -1)
        {
                perror("pclose(mysql)");
                return -1;
        }
        if(!WIFEXITED(status) || WEXITSTATUS(status) != 0)
        {
                fprintf(stderr, "DB query failed: %s\n", cmd);
                return -1;
        }
        return 0;
}

static void db_init_schema(void)
{
        const char *sql =
                "CREATE TABLE IF NOT EXISTS refill_log ("
                "id INT AUTO_INCREMENT PRIMARY KEY,"
                "machine_id INT NOT NULL,"
                "reason VARCHAR(20) NOT NULL,"
                "before_p1 INT DEFAULT NULL,"
                "before_p2 INT DEFAULT NULL,"
                "before_p3 INT DEFAULT NULL,"
                "filled_total INT NOT NULL DEFAULT 0,"
                "temp FLOAT NULL,"
                "humi FLOAT NULL,"
                "refill_at DATETIME DEFAULT CURRENT_TIMESTAMP,"
                "INDEX idx_refill_machine_time(machine_id, refill_at)"
                ");"
                "INSERT INTO machines(id, name, location, status) "
                "SELECT 1, 'VM_01', 'seoul', 'active' "
                "WHERE NOT EXISTS(SELECT 1 FROM machines WHERE id=1);"
                "INSERT INTO machines(id, name, location, status) "
                "SELECT 2, 'VM_02', 'busan', 'active' "
                "WHERE NOT EXISTS(SELECT 1 FROM machines WHERE id=2);"
                "INSERT INTO machine_products(machine_id, product_id, stock) "
                "SELECT 1, p.id, 10 FROM products p "
                "WHERE p.id BETWEEN 1 AND 3 "
                "AND NOT EXISTS(SELECT 1 FROM machine_products mp WHERE mp.machine_id=1 AND mp.product_id=p.id);"
                "INSERT INTO machine_products(machine_id, product_id, stock) "
                "SELECT 2, p.id, 10 FROM products p "
                "WHERE p.id BETWEEN 1 AND 3 "
                "AND NOT EXISTS(SELECT 1 FROM machine_products mp WHERE mp.machine_id=2 AND mp.product_id=p.id);";
        db_exec_sql(sql);
}

static int machine_id_from_client_location(const char *client_id, const char *location)
{
        if(client_id != NULL) {
                if(!strcmp(client_id, ID_VM02)) return BUSAN_MACHINE_ID;
                if(!strcmp(client_id, ID_VM01)) return LOCAL_MACHINE_ID;
        }
        if(location != NULL) {
                if(!strcasecmp(location, "busan") || !strcasecmp(location, "VM_02"))
                        return BUSAN_MACHINE_ID;
                if(!strcasecmp(location, "seoul") || !strcasecmp(location, "VM_01"))
                        return LOCAL_MACHINE_ID;
        }
        return LOCAL_MACHINE_ID;
}

typedef struct {
        int fd;
} STOCK_SYNC_ARG;

static void stock_sync_cb(const char *line, void *arg)
{
        STOCK_SYNC_ARG *ctx = (STOCK_SYNC_ARG *)arg;
        int product_id = 0;
        int stock = 0;
        char sendBuf[96];

        if(sscanf(line, "%d\t%d", &product_id, &stock) == 2)
        {
                snprintf(sendBuf, sizeof(sendBuf), "[%s]SETSTOCK@%d@%d\n", ID_SQL, product_id, stock);
                write(ctx->fd, sendBuf, strlen(sendBuf));
                printf("Stock sync: %s", sendBuf);
        }
}

static void db_sync_stock_fd(int client_fd, const char *client_id)
{
        char sql[256];
        int machine_id = machine_id_from_client_location(client_id, NULL);
        STOCK_SYNC_ARG ctx;
        ctx.fd = client_fd;

        if(client_id != NULL && (!strcmp(client_id, ID_BLT) || !strcmp(client_id, ID_STM32)))
                machine_id = LOCAL_MACHINE_ID;

        snprintf(sql, sizeof(sql),
                 "SELECT product_id, stock FROM machine_products WHERE machine_id=%d AND product_id BETWEEN 1 AND 3 ORDER BY product_id;",
                 machine_id);
        db_query_lines(sql, stock_sync_cb, &ctx);
}

static void db_vend(const char *from, int product_id, int remain, const char *location)
{
        char loc_esc[64];
        char sql[1024];
        int machine_id = machine_id_from_client_location(from, location);

        if(product_id < 1 || product_id > PRODUCT_COUNT) return;
        if(remain < 0) remain = 0;
        if(remain > MAX_STOCK) remain = MAX_STOCK;

        sql_escape(location, loc_esc, sizeof(loc_esc));

        snprintf(sql, sizeof(sql),
                 "SET @pid := %d;"
                 "SET @remain := %d;"
                 "SET @machine := %d;"
                 "SET @price := IFNULL((SELECT price FROM products WHERE id=@pid),0);"
                 "UPDATE products SET stock=@remain, datetime=NOW() WHERE id=@pid;"
                 "UPDATE machine_products SET stock=@remain WHERE machine_id=@machine AND product_id=@pid;"
                 "INSERT INTO sales_log(machine_id, product_id, date, time, sold, remain, price, revenue) "
                 "VALUES(@machine, @pid, CURDATE(), CURTIME(), 1, @remain, @price, @price);",
                 product_id, remain, machine_id);

        if(db_exec_sql(sql) == 0)
                printf("DB vend: machine_id=%d, product_id=%d, remain=%d, location=%s\n", machine_id, product_id, remain, loc_esc);
}

static void db_sensor(const char *from, float temp, float humi, int cds)
{
        char sql[512];
        int machine_id = machine_id_from_client_location(from, NULL);
        snprintf(sql, sizeof(sql),
                 "INSERT INTO sensor_log(machine_id, date, time, temp, humi, cds) "
                 "VALUES(%d, CURDATE(), CURTIME(), %.2f, %.2f, %d);",
                 machine_id, temp, humi, cds);
        db_exec_sql(sql);
}

static void missing_cb(const char *line, void *arg)
{
        MISSING_INFO *m = (MISSING_INFO *)arg;
        int local = 0;
        int all = 0;
        if(sscanf(line, "%d\t%d", &local, &all) == 2) {
                m->local_missing = local;
                m->all_missing = all;
        }
}

static int db_get_missing(MISSING_INFO *out)
{
        const char *sql =
                "SELECT "
                "COALESCE(SUM(CASE WHEN machine_id=1 THEN GREATEST(0,10-stock) ELSE 0 END),0) AS local_missing, "
                "COALESCE(SUM(GREATEST(0,10-stock)),0) AS all_missing "
                "FROM machine_products WHERE product_id BETWEEN 1 AND 3;";
        if(out == NULL) return -1;
        out->local_missing = 0;
        out->all_missing = 0;
        return db_query_lines(sql, missing_cb, out);
}

static void db_restock_all(const char *reason, bool has_sensor, float temp, float humi, MISSING_INFO before)
{
        char reason_esc[64];
        char temp_sql[32];
        char humi_sql[32];
        char sql[4096];

        sql_escape(reason, reason_esc, sizeof(reason_esc));

        if(has_sensor)
        {
                snprintf(temp_sql, sizeof(temp_sql), "%.2f", temp);
                snprintf(humi_sql, sizeof(humi_sql), "%.2f", humi);
        }
        else
        {
                strcpy(temp_sql, "NULL");
                strcpy(humi_sql, "NULL");
        }

        snprintf(sql, sizeof(sql),
                 "CREATE TABLE IF NOT EXISTS refill_log ("
                 "id INT AUTO_INCREMENT PRIMARY KEY,"
                 "machine_id INT NOT NULL,"
                 "reason VARCHAR(20) NOT NULL,"
                 "before_p1 INT DEFAULT NULL,"
                 "before_p2 INT DEFAULT NULL,"
                 "before_p3 INT DEFAULT NULL,"
                 "filled_total INT NOT NULL DEFAULT 0,"
                 "temp FLOAT NULL,"
                 "humi FLOAT NULL,"
                 "refill_at DATETIME DEFAULT CURRENT_TIMESTAMP,"
                 "INDEX idx_refill_machine_time(machine_id, refill_at)"
                 ");"
                 "INSERT INTO refill_log(machine_id, reason, before_p1, before_p2, before_p3, filled_total, temp, humi, refill_at) "
                 "SELECT machine_id, '%s', "
                 "MAX(CASE WHEN product_id=1 THEN stock END), "
                 "MAX(CASE WHEN product_id=2 THEN stock END), "
                 "MAX(CASE WHEN product_id=3 THEN stock END), "
                 "SUM(GREATEST(0,%d-stock)), %s, %s, NOW() "
                 "FROM machine_products WHERE product_id BETWEEN 1 AND 3 GROUP BY machine_id;"
                 "UPDATE products SET stock=%d, datetime=NOW() WHERE id BETWEEN 1 AND 3;"
                 "UPDATE machine_products SET stock=%d WHERE product_id BETWEEN 1 AND 3;",
                 reason_esc, MAX_STOCK, temp_sql, humi_sql, MAX_STOCK, MAX_STOCK);

        if(db_exec_sql(sql) == 0)
                printf("DB restock all: reason=%s, local_missing=%d, all_missing=%d, stock=%d\n",
                       reason_esc, before.local_missing, before.all_missing, MAX_STOCK);
}

void error_handling(char *msg)
{
        fputs(msg, stderr);
        fputc('\n', stderr);
        exit(1);
}

void log_file(char * msgstr)
{
        fputs(msgstr,stdout);
        fflush(stdout);
}

void getlocaltime(char * buf)
{
        struct tm *t;
        time_t tt;
        char wday[7][4] = {"Sun","Mon","Tue","Wed","Thu","Fri","Sat"};
        tt = time(NULL);
        if(errno == EFAULT)
                perror("time()");
        t = localtime(&tt);
        sprintf(buf,"[GETTIME]%02d.%02d.%02d %02d:%02d:%02d %s",t->tm_year+1900-2000,t->tm_mon+1,t->tm_mday,t->tm_hour,t->tm_min,t->tm_sec,wday[t->tm_wday]);
        return;
}

