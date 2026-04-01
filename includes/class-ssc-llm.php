<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'SSC_LLM' ) ) {

    class SSC_LLM {

        /**
         * Check if LLM auto-reply is configured and enabled.
         */
        public static function is_enabled() {
            $api_key  = SSC_Settings::get_option( 'ssc_llm_api_key', '' );
            $provider = SSC_Settings::get_option( 'ssc_llm_provider', '' );
            return ! empty( $api_key ) && ! empty( $provider );
        }

        /**
         * Classify a visitor question against canned responses using an LLM.
         *
         * Returns the best-matching canned response ID and text, or null if no good match.
         *
         * @param string $visitor_question The visitor's question/message.
         * @return array|null  { 'canned_id' => int, 'response' => string } or null.
         */
        public static function classify_question( $visitor_question ) {
            if ( ! self::is_enabled() ) {
                return null;
            }

            // Get all canned responses.
            $canned_data = SSC_Canned::get_all( array( 'per_page' => 200 ) );
            $canned      = $canned_data['items'];

            if ( empty( $canned ) ) {
                return null;
            }

            // Build a numbered list of canned Q&A pairs for the LLM.
            $canned_list = '';
            $canned_map  = array();
            foreach ( $canned as $idx => $item ) {
                $num = $idx + 1;
                $canned_map[ $num ] = $item;
                $q = ! empty( $item->question_summary ) ? $item->question_summary : '(general)';
                $canned_list .= sprintf( "[%d] Q: %s\nA: %s\n\n", $num, $q, $item->response_text );
            }

            $system_prompt = SSC_Settings::get_option( 'ssc_llm_system_prompt',
                'You are a classifier for a live chat support system. Given a visitor\'s question and a list of canned responses, pick the best matching canned response number. If none are a good match, respond with 0. Respond with ONLY the number, nothing else.'
            );

            $user_prompt = sprintf(
                "Visitor question: \"%s\"\n\nCanned responses:\n%s\nWhich canned response number best answers the visitor's question? Reply with only the number (or 0 if none match).",
                $visitor_question,
                $canned_list
            );

            $provider = SSC_Settings::get_option( 'ssc_llm_provider', 'openai' );
            $api_key  = SSC_Settings::get_option( 'ssc_llm_api_key', '' );
            $model    = SSC_Settings::get_option( 'ssc_llm_model', '' );

            $response_text = self::call_llm( $provider, $api_key, $model, $system_prompt, $user_prompt );

            if ( $response_text === null ) {
                return null;
            }

            // Parse the number from the response.
            $match_num = intval( trim( $response_text ) );

            if ( $match_num < 1 || ! isset( $canned_map[ $match_num ] ) ) {
                return null; // No good match.
            }

            $matched = $canned_map[ $match_num ];

            // Increment usage count.
            SSC_Canned::increment_usage( $matched->id );

            return array(
                'canned_id' => (int) $matched->id,
                'response'  => $matched->response_text,
            );
        }

        /**
         * Call the LLM API.
         *
         * @return string|null The response text, or null on failure.
         */
        private static function call_llm( $provider, $api_key, $model, $system_prompt, $user_prompt ) {
            switch ( $provider ) {
                case 'anthropic':
                    return self::call_anthropic( $api_key, $model ?: 'claude-haiku-4-5-20251001', $system_prompt, $user_prompt );
                case 'openai':
                default:
                    return self::call_openai( $api_key, $model ?: 'gpt-4o-mini', $system_prompt, $user_prompt );
            }
        }

        private static function call_openai( $api_key, $model, $system_prompt, $user_prompt ) {
            $body = array(
                'model'      => $model,
                'messages'   => array(
                    array( 'role' => 'system', 'content' => $system_prompt ),
                    array( 'role' => 'user', 'content' => $user_prompt ),
                ),
                'max_tokens' => 10,
                'temperature' => 0,
            );

            $response = wp_remote_post( 'https://api.openai.com/v1/chat/completions', array(
                'timeout' => 15,
                'headers' => array(
                    'Authorization' => 'Bearer ' . $api_key,
                    'Content-Type'  => 'application/json',
                ),
                'body' => wp_json_encode( $body ),
            ) );

            if ( is_wp_error( $response ) ) {
                error_log( 'SSC LLM OpenAI error: ' . $response->get_error_message() );
                return null;
            }

            $data = json_decode( wp_remote_retrieve_body( $response ), true );
            return isset( $data['choices'][0]['message']['content'] ) ? $data['choices'][0]['message']['content'] : null;
        }

        private static function call_anthropic( $api_key, $model, $system_prompt, $user_prompt ) {
            $body = array(
                'model'      => $model,
                'system'     => $system_prompt,
                'messages'   => array(
                    array( 'role' => 'user', 'content' => $user_prompt ),
                ),
                'max_tokens' => 10,
                'temperature' => 0,
            );

            $response = wp_remote_post( 'https://api.anthropic.com/v1/messages', array(
                'timeout' => 15,
                'headers' => array(
                    'x-api-key'         => $api_key,
                    'anthropic-version'  => '2023-06-01',
                    'Content-Type'       => 'application/json',
                ),
                'body' => wp_json_encode( $body ),
            ) );

            if ( is_wp_error( $response ) ) {
                error_log( 'SSC LLM Anthropic error: ' . $response->get_error_message() );
                return null;
            }

            $data = json_decode( wp_remote_retrieve_body( $response ), true );
            return isset( $data['content'][0]['text'] ) ? $data['content'][0]['text'] : null;
        }
    }

}
